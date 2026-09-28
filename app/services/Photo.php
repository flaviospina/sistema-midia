<?php
// app/services/Photo.php — foto de perfil: valida, redimensiona com GD e remove EXIF
declare(strict_types=1);

final class Photo
{
    private const MAX_SIDE = 512;

    /**
     * Processa o upload $_FILES[$field] e devolve o nome do arquivo gravado,
     * null se não veio arquivo, ou lança InvalidArgumentException com mensagem amigável.
     */
    public static function store(string $field): ?string
    {
        $file = $_FILES[$field] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A foto excede o tamanho permitido pelo servidor.',
                default => 'Falha no envio da foto. Tente novamente.',
            });
        }
        if ($file['size'] > PHOTO_MAX_MB * 1024 * 1024) {
            throw new InvalidArgumentException('A foto deve ter no máximo ' . PHOTO_MAX_MB . ' MB.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Envio inválido.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Use uma foto JPG, PNG ou WebP.');
        }

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        };
        if (!$src) {
            throw new InvalidArgumentException('Não foi possível ler a imagem.');
        }

        // Corrige orientação pelo EXIF (câmeras de celular)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            $src = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }

        // Recorte central quadrado + redimensionamento
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $out = min(self::MAX_SIDE, $side);
        $dst = imagecreatetruecolor($out, $out);
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $out, $out, $side, $side);

        $dir = STORAGE_PATH . '/photos';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new RuntimeException('Pasta de fotos não pôde ser criada.');
        }
        $name = bin2hex(random_bytes(16)) . '.jpg';
        // Regravar como JPEG novo descarta todo EXIF/GPS
        if (!imagejpeg($dst, $dir . '/' . $name, 85)) {
            throw new RuntimeException('Falha ao gravar a foto.');
        }
        imagedestroy($src);
        imagedestroy($dst);
        return $name;
    }

    public static function delete(?string $name): void
    {
        if ($name && preg_match('/^[a-f0-9]{32}\.jpg$/', $name)) {
            @unlink(STORAGE_PATH . '/photos/' . $name);
        }
    }

    /** Envia a foto ao navegador (sempre via PHP, nunca por link direto). */
    public static function serve(?string $name): never
    {
        $path = $name && preg_match('/^[a-f0-9]{32}\.jpg$/', $name) ? STORAGE_PATH . '/photos/' . $name : null;
        if (!$path || !is_file($path)) {
            abort(404);
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
