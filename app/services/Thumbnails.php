<?php
// app/services/Thumbnails.php — miniaturas e versão exibida (sem EXIF) com GD
declare(strict_types=1);

final class Thumbnails
{
    private const THUMB_MAX = 480;      // maior lado da miniatura
    private const DISPLAY_MAX = 1920;   // maior lado da versão exibida
    private const MAX_PIXELS = 36_000_000; // acima disso GD estoura memória em hospedagem compartilhada

    /**
     * Gera miniatura de uma imagem. Devolve caminho temporário do JPEG ou null (formato não suportado / imagem grande demais).
     */
    public static function fromImage(string $path, string $ext): ?string
    {
        $src = self::open($path, $ext);
        if (!$src) {
            return null;
        }
        $out = self::resizeToTmp($src, self::THUMB_MAX);
        imagedestroy($src);
        return $out;
    }

    /**
     * Versão exibida de um JPEG: reencodada (sem EXIF/GPS) e limitada a 1920 px.
     * Para outros formatos não há EXIF relevante; devolve null (usa-se o original).
     */
    public static function displayVersion(string $path, string $ext): ?string
    {
        if (!in_array($ext, ['jpg', 'jpeg'], true)) {
            return null;
        }
        $src = self::open($path, $ext);
        if (!$src) {
            return null;
        }
        $out = self::resizeToTmp($src, self::DISPLAY_MAX, 88);
        imagedestroy($src);
        return $out;
    }

    /** Miniatura enviada pelo navegador (vídeo capturado em canvas) como data URL. */
    public static function fromDataUrl(string $dataUrl): ?string
    {
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > 3 * 1024 * 1024) {
            return null;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            return null;
        }
        $out = self::resizeToTmp($src, self::THUMB_MAX);
        imagedestroy($src);
        return $out;
    }

    /** Dimensões de uma imagem (sem carregar em memória). */
    public static function dimensions(string $path): ?array
    {
        $info = @getimagesize($path);
        return $info ? ['width' => (int) $info[0], 'height' => (int) $info[1]] : null;
    }

    private static function open(string $path, string $ext): ?GdImage
    {
        $info = @getimagesize($path);
        if (!$info || ($info[0] * $info[1]) > self::MAX_PIXELS) {
            return null;
        }
        $img = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'gif'         => @imagecreatefromgif($path),
            'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default       => false,
        };
        if (!$img) {
            return null;
        }
        if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $img = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }
        return $img ?: null;
    }

    private static function resizeToTmp(GdImage $src, int $maxSide, int $quality = 82): ?string
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        // Fundo branco para PNG/GIF com transparência
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $tmp = tempnam(Storage::tmpDir(), 'thumb_');
        if ($tmp === false || !imagejpeg($dst, $tmp, $quality)) {
            imagedestroy($dst);
            return null;
        }
        imagedestroy($dst);
        return $tmp;
    }
}
