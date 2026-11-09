<?php
// app/services/SocialCard.php — imagem de prévia (1200×630) usada nas metatags Open Graph/Twitter
// dos links públicos: foto de capa recortada, faixa com nome do culto, data e logo da igreja. Gerada com GD e
// guardada em cache (storage/thumbs/cards); refeita quando a capa, o texto ou a logo mudam.
declare(strict_types=1);

final class SocialCard
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    public static function dir(): string
    {
        return THUMBS_PATH . '/cards';
    }

    private static function font(bool $bold): ?string
    {
        $f = APP_ROOT . '/assets/fonts/LiberationSans-' . ($bold ? 'Bold' : 'Regular') . '.ttf';
        return is_file($f) && function_exists('imagettftext') ? $f : null;
    }

    /** Cartão de um álbum compartilhado. $cover é a foto de capa (ou null). Devolve o caminho do JPEG. */
    public static function forAlbum(array $link, ?array $cover, int $count): string
    {
        $title = (string) $link['album_name'];
        $sub = ucfirst(weekday_pt($link['event_date'])) . ', ' . format_date($link['event_date']) . ' · ' . $count . ' foto' . ($count === 1 ? '' : 's');
        return self::build('a' . (int) $link['album_id'], $cover, $title, $sub);
    }

    /** Cartão de uma foto compartilhada. */
    public static function forPhoto(array $photo): string
    {
        $title = (string) ($photo['caption'] ?: $photo['album_name']);
        $sub = ($photo['caption'] ? $photo['album_name'] . ' · ' : '') . ucfirst(weekday_pt($photo['event_date'])) . ', ' . format_date($photo['event_date']);
        return self::build('p' . (int) $photo['id'], $photo, $title, $sub);
    }

    private static function sourcePath(?array $photo): ?string
    {
        if (!$photo) {
            return null;
        }
        $d = Photo::displayPath((int) $photo['id']);
        if (is_file($d)) {
            return $d;
        }
        $o = Photo::path($photo);
        return is_file($o) ? $o : null;
    }

    private static function build(string $key, ?array $photo, string $title, string $sub): string
    {
        $src = self::sourcePath($photo);
        $logo = Watermark::hasLogo() ? Watermark::logoPath() : null;
        $sig = md5(implode('|', [$key, $src ? $src . ':' . filemtime($src) : '-', $logo ? filemtime($logo) : '-', $title, $sub, 'v2']));
        $out = self::dir() . '/' . $key . '-' . $sig . '.jpg';
        if (is_file($out)) {
            return $out;
        }
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0750, true);
        }
        foreach (glob(self::dir() . '/' . $key . '-*.jpg') ?: [] as $old) {   // versões antigas do mesmo item
            @unlink($old);
        }
        $img = self::render($src, $logo, $title, $sub);
        imagejpeg($img, $out, 86);
        imagedestroy($img);
        return $out;
    }

    private static function render(?string $src, ?string $logo, string $title, string $sub): GdImage
    {
        $W = self::WIDTH;
        $H = self::HEIGHT;
        $c = imagecreatetruecolor($W, $H);
        $navy = imagecolorallocate($c, 11, 31, 58);
        imagefilledrectangle($c, 0, 0, $W, $H, $navy);

        // Foto de capa preenchendo o cartão (recorte central)
        $photo = $src ? Image::open($src, Image::detect($src) ?? 'image/jpeg') : null;
        if ($photo) {
            $sw = imagesx($photo);
            $sh = imagesy($photo);
            $scale = max($W / $sw, $H / $sh);
            $cw = (int) round($W / $scale);
            $ch = (int) round($H / $scale);
            imagecopyresampled($c, $photo, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $W, $H, $cw, $ch);
            imagedestroy($photo);
        } else {
            self::decorativeBackground($c);
        }

        // Faixa escura degradê na parte inferior (legibilidade do texto)
        $bandTop = (int) ($H * 0.50);
        for ($y = $bandTop; $y < $H; $y++) {
            $t = ($y - $bandTop) / ($H - $bandTop);          // 0 → 1
            $alpha = (int) round(127 - 127 * min(1, $t * 1.35)); // 127 = transparente … 0 = opaco
            imageline($c, 0, $y, $W, $y, imagecolorallocatealpha($c, 11, 31, 58, $alpha));
        }

        $gold = imagecolorallocate($c, 201, 162, 74);
        $white = imagecolorallocate($c, 255, 255, 255);
        $soft = imagecolorallocate($c, 226, 230, 238);

        // Logo da igreja no canto superior direito, sobre uma pastilha translúcida
        if ($logo && ($lg = @imagecreatefrompng($logo))) {
            $lh = 120;
            $lw = (int) round(imagesx($lg) * $lh / imagesy($lg));
            if ($lw > 360) {
                $lw = 360;
                $lh = (int) round(imagesy($lg) * $lw / imagesx($lg));
            }
            $pad = 18;
            $x = $W - 48 - $lw;
            $y = 40;
            self::roundedRect($c, $x - $pad, $y - $pad, $x + $lw + $pad, $y + $lh + $pad, 18, imagecolorallocatealpha($c, 255, 255, 255, 40));
            imagealphablending($c, true);
            imagecopyresampled($c, $lg, $x, $y, 0, 0, $lw, $lh, imagesx($lg), imagesy($lg));
            imagedestroy($lg);
        }

        $bold = self::font(true);
        $reg = self::font(false);
        $margin = 56;
        $maxW = $W - 2 * $margin;
        if ($bold && $reg) {
            // Título: até 2 linhas, tamanho reduzido se necessário
            $size = 58;
            do {
                $lines = self::wrap($bold, $size, $title, $maxW);
                $size -= 4;
            } while (count($lines) > 2 && $size >= 34);
            $size += 4;
            if (count($lines) > 2) {
                $lines = array_slice($lines, 0, 2);
                $lines[1] = rtrim(mb_substr($lines[1], 0, max(0, mb_strlen($lines[1]) - 1))) . '…';
            }
            $lineH = (int) round($size * 1.22);
            $subSize = 28;
            $footSize = 20;
            $baseFoot = $H - 44;
            $baseSub = $baseFoot - 46;
            $baseTitle = $baseSub - 44;
            // linhas do título de baixo para cima
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $y = $baseTitle - (count($lines) - 1 - $i) * $lineH;
                imagettftext($c, $size, 0, $margin + 2, $y + 2, imagecolorallocatealpha($c, 0, 0, 0, 70), $bold, $lines[$i]);
                imagettftext($c, $size, 0, $margin, $y, $white, $bold, $lines[$i]);
            }
            // filete dourado acima do título
            $topLine = $baseTitle - (count($lines) - 1) * $lineH - $size - 22;
            imagefilledrectangle($c, $margin, $topLine, $margin + 110, $topLine + 6, $gold);
            imagettftext($c, $subSize, 0, $margin, $baseSub, $gold, $reg, $sub);
            imagettftext($c, $footSize, 0, $margin, $baseFoot, $soft, $reg, 'AD Moema · Envio de Mídias');
        } else {
            // Sem fonte TrueType: texto básico do GD
            imagefilledrectangle($c, $margin, $H - 150, $margin + 110, $H - 144, $gold);
            imagestring($c, 5, $margin, $H - 130, self::ascii($title), $white);
            imagestring($c, 3, $margin, $H - 95, self::ascii($sub), $gold);
            imagestring($c, 2, $margin, $H - 60, 'AD Moema - Envio de Midias', $soft);
        }
        return $c;
    }

    /** Fundo quando o álbum não tem foto: círculos dourados suaves sobre o azul. */
    private static function decorativeBackground(GdImage $c): void
    {
        $W = self::WIDTH;
        $H = self::HEIGHT;
        imagealphablending($c, true);
        foreach ([[980, 120, 520], [220, 560, 420], [1120, 560, 260]] as [$x, $y, $r]) {
            for ($i = $r; $i > 0; $i -= 6) {
                imagefilledellipse($c, $x, $y, $i, $i, imagecolorallocatealpha($c, 201, 162, 74, 124));
            }
        }
    }

    /** Quebra o texto em linhas que caibam em $maxW pixels. @return list<string> */
    private static function wrap(string $font, int $size, string $text, int $maxW): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            $box = imagettfbbox($size, 0, $font, $try);
            if ($box && ($box[2] - $box[0]) > $maxW && $cur !== '') {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines ?: [''];
    }

    /** Retângulo arredondado translúcido, desenhado numa camada própria para não escurecer onde as formas se sobrepõem. */
    private static function roundedRect(GdImage $c, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $w = $x2 - $x1;
        $h = $y2 - $y1;
        $layer = imagecreatetruecolor($w, $h);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefill($layer, 0, 0, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        $rgba = imagecolorsforindex($c, $color);
        $fill = imagecolorallocatealpha($layer, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);
        imagefilledrectangle($layer, $r, 0, $w - $r - 1, $h - 1, $fill);
        imagefilledrectangle($layer, 0, $r, $w - 1, $h - $r - 1, $fill);
        foreach ([[$r, $r], [$w - $r - 1, $r], [$r, $h - $r - 1], [$w - $r - 1, $h - $r - 1]] as [$cx, $cy]) {
            imagefilledellipse($layer, $cx, $cy, 2 * $r, 2 * $r, $fill);
        }
        imagealphablending($c, true);
        imagecopy($c, $layer, $x1, $y1, 0, 0, $w, $h);
        imagedestroy($layer);
    }

    private static function ascii(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $t !== false ? $t : $s;
    }

    /** Remove cartões não usados há mais de $days dias (cron). */
    public static function purge(int $days = 30): int
    {
        $n = 0;
        foreach (glob(self::dir() . '/*.jpg') ?: [] as $f) {
            if (filemtime($f) < time() - $days * 86400 && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
