<?php
// app/services/Delivery.php — envio de arquivos ao navegador com HTTP Range, nosniff e Content-Disposition
declare(strict_types=1);

final class Delivery
{
    /**
     * @param string $path      caminho local do arquivo
     * @param string $mime      MIME seguro (derivado da extensão)
     * @param string $filename  nome sugerido ao usuário
     * @param bool   $inline    true = exibir no navegador; false = baixar
     */
    public static function send(string $path, string $mime, string $filename, bool $inline = false, int $cacheSeconds = 0): never
    {
        if (!is_file($path) || !is_readable($path)) {
            abort(404, 'Arquivo não encontrado no armazenamento.');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Encerra a sessão para não bloquear outras requisições durante o envio
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        set_time_limit(0);
        ignore_user_abort(false);

        $size = (int) filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;

        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m)) {
            if ($m[1] === '' && $m[2] === '') {
                self::rangeNotSatisfiable($size);
            }
            if ($m[1] === '') {                     // últimos N bytes
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
            }
            if ($start > $end || $start >= $size) {
                self::rangeNotSatisfiable($size);
            }
            $status = 206;
        }

        $safeName = str_replace(['"', "\r", "\n"], '', $filename);
        $asciiName = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $safeName) ?: 'arquivo';
        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName);

        http_response_code($status);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        header('Cache-Control: ' . ($cacheSeconds > 0 ? "private, max-age={$cacheSeconds}" : 'private, no-store'));
        if ($status === 206) {
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }

        $fp = fopen($path, 'rb');
        if (!$fp) {
            exit;
        }
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        $chunk = 512 * 1024;
        while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
            $buf = fread($fp, min($chunk, $remaining));
            if ($buf === false) {
                break;
            }
            echo $buf;
            flush();
            $remaining -= strlen($buf);
        }
        fclose($fp);
        exit;
    }

    private static function rangeNotSatisfiable(int $size): never
    {
        http_response_code(416);
        header("Content-Range: bytes */{$size}");
        exit;
    }
}
