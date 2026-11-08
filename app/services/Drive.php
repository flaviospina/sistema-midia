<?php
// app/services/Drive.php — leitura de uma pasta compartilhada do Google Drive ("qualquer pessoa com o link") pela Drive API v3, via cURL
declare(strict_types=1);

final class Drive
{
    private const API = 'https://www.googleapis.com/drive/v3/';
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    public static function ready(): bool
    {
        return Setting::get('google_api_key') !== '';
    }

    /** Extrai o ID da pasta de qualquer formato de link do Drive (ou aceita o ID puro). */
    public static function folderId(string $link): ?string
    {
        $link = trim($link);
        if (preg_match('#/folders/([A-Za-z0-9_-]{10,})#', $link, $m) || preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', $link, $m)) {
            return $m[1];
        }
        return preg_match('/^[A-Za-z0-9_-]{10,}$/', $link) ? $link : null;
    }

    private static function key(): string
    {
        $k = Setting::get('google_api_key');
        if ($k === '') {
            throw new RuntimeException('O administrador precisa informar a chave da API do Google em Configurações.');
        }
        return $k;
    }

    private static function call(string $path, array $params): array
    {
        $ch = curl_init(self::API . $path . '?' . http_build_query($params + ['key' => self::key()]));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            throw new RuntimeException('Não foi possível falar com o Google Drive: ' . $err);
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Não foi possível falar com o Google Drive (HTTP ' . $status . '). Verifique a conexão do servidor com a internet e tente de novo.');
        }
        if ($status >= 400) {
            $msg = (string) ($data['error']['message'] ?? '');
            throw new RuntimeException(match ($status) {
                400, 403 => str_contains($msg, 'API key') || str_contains($msg, 'disabled') || str_contains($msg, 'not been used') ? 'Chave da API inválida ou API do Google Drive não ativada no projeto do Google Cloud.' : 'O Google recusou o acesso à pasta. Ela precisa estar compartilhada como "Qualquer pessoa com o link".',
                404 => 'Pasta não encontrada. Confira o link e se ela está compartilhada como "Qualquer pessoa com o link".',
                default => 'Erro do Google Drive (HTTP ' . $status . '): ' . ($msg ?: 'resposta inesperada'),
            });
        }
        return $data;
    }

    /** Nome da pasta (para conferência na tela). */
    public static function folderInfo(string $folderId): array
    {
        $f = self::call('files/' . rawurlencode($folderId), ['fields' => 'id,name,mimeType', 'supportsAllDrives' => 'true']);
        if (($f['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') {
            throw new RuntimeException('O link não é de uma pasta do Google Drive.');
        }
        return $f;
    }

    /** Lista as imagens da pasta (sem subpastas). Devolve [id, name, mimeType, size, md5, width, height, time]. */
    public static function listImages(string $folderId): array
    {
        $out = [];
        $token = null;
        do {
            $params = ['q' => sprintf("'%s' in parents and trashed = false", str_replace("'", "\\'", $folderId)), 'fields' => 'nextPageToken,files(id,name,mimeType,size,md5Checksum,imageMediaMetadata(width,height,time))', 'pageSize' => 200, 'orderBy' => 'name', 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true'];
            if ($token) {
                $params['pageToken'] = $token;
            }
            $r = self::call('files', $params);
            foreach ($r['files'] ?? [] as $f) {
                $mime = (string) ($f['mimeType'] ?? '');
                $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($mime, self::IMAGE_MIMES, true) && !in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
                    continue;
                }
                $out[] = ['id' => (string) $f['id'], 'name' => (string) $f['name'], 'mime' => $mime, 'size' => (int) ($f['size'] ?? 0), 'md5' => $f['md5Checksum'] ?? null, 'width' => $f['imageMediaMetadata']['width'] ?? null, 'height' => $f['imageMediaMetadata']['height'] ?? null, 'time' => $f['imageMediaMetadata']['time'] ?? null];
            }
            $token = $r['nextPageToken'] ?? null;
        } while ($token && count($out) < 2000);
        return $out;
    }

    /** Baixa o conteúdo de um arquivo para $dest (gravação direta em disco, sem carregar na memória). */
    public static function download(string $fileId, string $dest): void
    {
        $fp = fopen($dest, 'wb');
        if (!$fp) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário.');
        }
        $ch = curl_init(self::API . 'files/' . rawurlencode($fileId) . '?' . http_build_query(['alt' => 'media', 'supportsAllDrives' => 'true', 'key' => self::key()]));
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_MAXFILESIZE => UPLOAD_MAX_BYTES]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $status >= 400) {
            @unlink($dest);
            throw new RuntimeException($status === 403 ? 'O Google não permitiu baixar este arquivo (confira o compartilhamento).' : 'Falha ao baixar do Google Drive' . ($err ? ': ' . $err : ' (HTTP ' . $status . ')') . '.');
        }
    }
}
