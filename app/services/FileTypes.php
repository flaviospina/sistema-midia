<?php
// app/services/FileTypes.php — extensões permitidas, MIME aceito por extensão e categoria padrão
declare(strict_types=1);

final class FileTypes
{
    public const CATEGORIES = [
        'foto'               => 'Foto',
        'video'              => 'Vídeo',
        'audio'              => 'Áudio',
        'arte_final'         => 'Arte final',
        'documento'          => 'Documento',
        'identidade_visual'  => 'Identidade visual',
    ];

    /** extensão => [mimes aceitos]. SVG e HTML ficam de fora de propósito. */
    private const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'heic' => ['image/heic', 'image/heif', 'image/heic-sequence', 'application/octet-stream'],
        'mp4'  => ['video/mp4', 'video/quicktime', 'application/octet-stream'],
        'mov'  => ['video/quicktime', 'video/mp4', 'application/octet-stream'],
        'm4v'  => ['video/x-m4v', 'video/mp4', 'application/octet-stream'],
        'mp3'  => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4', 'application/octet-stream'],
        'wav'  => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'pdf'  => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'psd'  => ['image/vnd.adobe.photoshop', 'application/octet-stream'],
        'ai'   => ['application/pdf', 'application/postscript', 'application/illustrator', 'application/octet-stream'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];

    /** Nunca aceitos, mesmo que a extensão engane. */
    private const FORBIDDEN_MIME_PREFIXES = ['text/html', 'image/svg', 'application/x-httpd', 'text/x-php', 'application/x-php',
        'application/x-msdownload', 'application/x-dosexec', 'application/x-executable', 'application/x-sh', 'text/javascript',
        'application/javascript', 'application/x-shellscript', 'application/vnd.microsoft.portable-executable'];

    public static function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED);
    }

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function isAllowedExtension(string $ext): bool
    {
        return isset(self::ALLOWED[$ext]);
    }

    /** Verifica se o MIME detectado pelo finfo é compatível com a extensão. */
    public static function isCompatible(string $ext, string $mime): bool
    {
        $mime = strtolower(trim((string) explode(';', $mime)[0]));
        foreach (self::FORBIDDEN_MIME_PREFIXES as $bad) {
            if (str_starts_with($mime, $bad)) {
                return false;
            }
        }
        return in_array($mime, self::ALLOWED[$ext] ?? [], true);
    }

    public static function defaultCategory(string $ext, string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') && !in_array($ext, ['psd'], true) => 'foto',
            str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'm4v'], true) => 'video',
            str_starts_with($mime, 'audio/') || in_array($ext, ['mp3', 'm4a', 'wav'], true) => 'audio',
            in_array($ext, ['psd', 'ai'], true) => 'arte_final',
            default => 'documento',
        };
    }

    /** Tipos que o navegador consegue exibir inline. */
    public static function isPreviewable(string $mime, string $ext): bool
    {
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'm4v', 'mp3', 'm4a', 'wav', 'pdf'], true);
    }

    public static function isImage(string $ext): bool
    {
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public static function isVideo(string $ext): bool
    {
        return in_array($ext, ['mp4', 'mov', 'm4v'], true);
    }

    public static function isAudio(string $ext): bool
    {
        return in_array($ext, ['mp3', 'm4a', 'wav'], true);
    }

    /** MIME seguro para entrega, a partir da extensão (não confia no que o cliente disse). */
    public static function deliveryMime(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
            'heic' => 'image/heic', 'mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }

    /** Ícone Bootstrap para a categoria/extensão. */
    public static function icon(string $category, string $ext = ''): string
    {
        return match (true) {
            $ext === 'pdf' => 'bi-file-earmark-pdf',
            in_array($ext, ['docx'], true) => 'bi-file-earmark-word',
            in_array($ext, ['xlsx'], true) => 'bi-file-earmark-excel',
            in_array($ext, ['pptx'], true) => 'bi-file-earmark-slides',
            in_array($ext, ['zip'], true) => 'bi-file-earmark-zip',
            in_array($ext, ['psd', 'ai'], true) => 'bi-vector-pen',
            $category === 'foto' => 'bi-image',
            $category === 'video' => 'bi-film',
            $category === 'audio' => 'bi-music-note-beamed',
            $category === 'arte_final' => 'bi-brush',
            $category === 'identidade_visual' => 'bi-palette',
            default => 'bi-file-earmark',
        };
    }
}
