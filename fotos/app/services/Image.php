<?php
// app/services/Image.php — processamento com GD: validação, orientação EXIF, miniatura e versão de exibição sem metadados
declare(strict_types=1);

final class Image
{
    /** MIME real pelo conteúdo; null se não for uma imagem aceita. */
    public static function detect(string $path): ?string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (isset(Photo::ALLOWED[$mime])) {
            return $mime;
        }
        // HEIC: finfo antigo devolve application/octet-stream; confere a assinatura "ftypheic/heix/mif1"
        $head = (string) file_get_contents($path, false, null, 0, 32);
        if (preg_match('/ftyp(heic|heix|hevc|mif1|msf1)/', $head)) {
            return 'image/heic';
        }
        return null;
    }

    /** Abre a imagem (JPEG/PNG/WebP) já com a orientação corrigida; null se GD não ler (ex.: HEIC). */
    public static function open(string $path, string $mime): ?GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
        if (!$img) {
            return null;
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $img = match ($o) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            } ?: $img;
        }
        return $img;
    }

    /** Data da foto pelo EXIF (DateTimeOriginal), em formato MySQL. */
    public static function takenAt(string $path, string $mime): ?string
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return null;
        }
        $v = @exif_read_data($path)['DateTimeOriginal'] ?? null;
        if (!$v || !preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}:\d{2}:\d{2})$/', $v, $m)) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}" : null;
    }

    /**
     * Gera miniatura (quadrada, THUMB_SIDE) e versão de exibição (lado maior DISPLAY_MAX_SIDE) em JPEG novo,
     * o que descarta EXIF/GPS. Devolve [width, height, has_thumb, has_display].
     */
    public static function derivatives(string $path, string $mime, int $photoId): array
    {
        $src = self::open($path, $mime);
        if (!$src) {
            return ['width' => null, 'height' => null, 'has_thumb' => 0, 'has_display' => 0];
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $dir = dirname(Photo::thumbPath($photoId));
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        // Miniatura quadrada (recorte central)
        $side = min($w, $h);
        $t = imagecreatetruecolor(THUMB_SIDE, THUMB_SIDE);
        imagecopyresampled($t, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), THUMB_SIDE, THUMB_SIDE, $side, $side);
        $okT = imagejpeg($t, Photo::thumbPath($photoId), 82);
        imagedestroy($t);
        // Exibição
        $scale = min(1, DISPLAY_MAX_SIDE / max($w, $h));
        $dw = max(1, (int) round($w * $scale));
        $dh = max(1, (int) round($h * $scale));
        $d = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($d, $src, 0, 0, 0, 0, $dw, $dh, $w, $h);
        $okD = imagejpeg($d, Photo::displayPath($photoId), 88);
        imagedestroy($d);
        imagedestroy($src);
        return ['width' => $w, 'height' => $h, 'has_thumb' => $okT ? 1 : 0, 'has_display' => $okD ? 1 : 0];
    }

    public static function memoryOk(string $path): bool
    {
        $info = @getimagesize($path);
        if (!$info) {
            return true; // HEIC etc.: GD não abre mesmo
        }
        $need = $info[0] * $info[1] * 5 + 8 * 1024 * 1024;
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit > 0 && $need > $limit - memory_get_usage()) {
            @ini_set('memory_limit', (string) min(1024, (int) ceil(($need + memory_get_usage()) / 1048576) + 64) . 'M');
            $limit = self::bytes((string) ini_get('memory_limit'));
        }
        return $limit <= 0 || $need <= $limit - memory_get_usage();
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }
}
