<?php
// app/services/Watermark.php — logo da igreja como marca d'água, gravada na foto no momento da importação
// (antes de qualquer ajuste). Usa GD; logo em PNG com transparência.
declare(strict_types=1);

final class Watermark
{
    private static bool $force = false;

    public static function logoPath(): string
    {
        return STORAGE_PATH . '/branding/logo.png';
    }

    public static function hasLogo(): bool
    {
        return is_file(self::logoPath());
    }

    public static function enabled(): bool
    {
        return (self::$force || Setting::get('watermark_enabled', '0') === '1') && self::hasLogo();
    }

    /** Grava a logo enviada pelo administrador como PNG (mantendo transparência), com lado maior de até 1200 px. */
    public static function storeLogo(array $file): void
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Falha no envio da logo.');
        }
        if ((int) $file['size'] > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('A logo deve ter no máximo 8 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $src = match ($mime) {
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            'image/gif'  => @imagecreatefromgif($file['tmp_name']),
            default      => false,
        };
        if (!$src) {
            throw new InvalidArgumentException('Use uma imagem PNG (de preferência com fundo transparente), JPG, WebP ou GIF.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 1200 / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $dir = dirname(self::logoPath());
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new RuntimeException('Não foi possível criar a pasta storage/branding.');
        }
        if (!imagepng($dst, self::logoPath(), 6)) {
            throw new RuntimeException('Não foi possível gravar a logo.');
        }
        imagedestroy($src);
        imagedestroy($dst);
    }

    public static function removeLogo(): void
    {
        @unlink(self::logoPath());
    }

    /** Aplica a logo na imagem (em memória) conforme as configurações. Não faz nada se desligada. */
    public static function apply(GdImage $img): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $logo = @imagecreatefrompng(self::logoPath());
        if (!$logo) {
            return false;
        }
        $iw = imagesx($img);
        $ih = imagesy($img);
        $lw0 = imagesx($logo);
        $lh0 = imagesy($logo);
        $pct = max(5, min(50, (int) Setting::get('watermark_width', '18')));
        $lw = max(16, (int) round($iw * $pct / 100));
        $lh = max(1, (int) round($lh0 * $lw / $lw0));
        if ($lh > $ih * 0.4) {
            $lh = (int) round($ih * 0.4);
            $lw = max(1, (int) round($lw0 * $lh / $lh0));
        }
        $scaled = imagecreatetruecolor($lw, $lh);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $lw, $lh, $lw0, $lh0);
        imagedestroy($logo);
        $opacity = max(10, min(100, (int) Setting::get('watermark_opacity', '85')));
        if ($opacity < 100) {
            // COLORIZE com alpha aumenta a transparência de todos os pixels (0 = opaco, 127 = invisível)
            imagefilter($scaled, IMG_FILTER_COLORIZE, 0, 0, 0, (int) round(127 * (100 - $opacity) / 100));
        }
        $margin = (int) round($iw * max(0, min(10, (int) Setting::get('watermark_margin', '2'))) / 100);
        [$x, $y] = match (Setting::get('watermark_position', 'superior-direito')) {
            'superior-esquerdo' => [$margin, $margin],
            'inferior-direito'  => [$iw - $lw - $margin, $ih - $lh - $margin],
            'inferior-esquerdo' => [$margin, $ih - $lh - $margin],
            default             => [$iw - $lw - $margin, $margin],
        };
        imagealphablending($img, true);
        imagecopy($img, $scaled, max(0, $x), max(0, $y), 0, 0, $lw, $lh);
        imagedestroy($scaled);
        return true;
    }

    /**
     * Grava a logo diretamente no arquivo da foto (JPEG/PNG/WebP), corrigindo a orientação EXIF.
     * Devolve true se marcou; false se desligada ou formato não suportado (ex.: HEIC).
     */
    public static function stamp(string $path, string $mime): bool
    {
        if (!self::enabled() || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }
        Image::memoryOk($path);
        $img = Image::open($path, $mime);
        if (!$img) {
            return false;
        }
        if (!self::apply($img)) {
            imagedestroy($img);
            return false;
        }
        $ok = match ($mime) {
            'image/png'  => imagepng($img, $path, 6),
            'image/webp' => imagewebp($img, $path, 90),
            default      => imagejpeg($img, $path, 92),
        };
        imagedestroy($img);
        return (bool) $ok;
    }

    /** Amostra para a tela de configurações: a logo aplicada sobre um fundo neutro 600×400 (JPEG em memória). */
    public static function preview(): ?string
    {
        if (!self::hasLogo()) {
            return null;
        }
        $img = imagecreatetruecolor(600, 400);
        for ($y = 0; $y < 400; $y++) {
            $t = $y / 400;
            imageline($img, 0, $y, 600, $y, imagecolorallocate($img, (int) (30 + 90 * $t), (int) (60 + 100 * $t), (int) (110 + 90 * $t)));
        }
        self::$force = true;
        self::apply($img);
        self::$force = false;
        ob_start();
        imagejpeg($img, null, 85);
        imagedestroy($img);
        return (string) ob_get_clean();
    }
}
