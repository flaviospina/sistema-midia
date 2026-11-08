<?php
// app/services/Social.php — publicação em Instagram / Página do Facebook pela Meta Graph API (cURL)
declare(strict_types=1);

final class Social
{
    private const API = 'https://graph.facebook.com/';

    /** Chamada à Graph API. Devolve array decodificado ou lança RuntimeException com mensagem amigável. */
    private static function call(string $method, string $path, array $params): array
    {
        $url = self::API . META_GRAPH_VERSION . '/' . ltrim($path, '/');
        $ch = curl_init();
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true];
        if ($method === 'GET') {
            $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
        } else {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            throw new RuntimeException('Não foi possível falar com a Meta: ' . $err);
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inesperada da Meta (HTTP ' . $status . ').');
        }
        if (isset($data['error'])) {
            $e = $data['error'];
            $msg = $e['error_user_msg'] ?? $e['message'] ?? 'erro desconhecido';
            Logger::info('Meta Graph API erro', ['path' => $path, 'code' => $e['code'] ?? null, 'msg' => $msg]);
            throw new RuntimeException(self::friendly((int) ($e['code'] ?? 0), $msg));
        }
        return $data;
    }

    private static function friendly(int $code, string $msg): string
    {
        return match ($code) {
            190 => 'O token de acesso expirou ou foi revogado. Gere um novo em Meus dados → Redes sociais.',
            10, 200, 203 => 'A conta não tem permissão para publicar. Confira as permissões do token (instagram_content_publish / pages_manage_posts).',
            default => 'A Meta recusou a publicação: ' . $msg,
        };
    }

    /**
     * Com um token de usuário, descobre as Páginas e as contas Instagram profissionais ligadas a elas.
     * Devolve [['page_id','page_name','page_token','ig_id','ig_username']...]
     */
    public static function discover(string $userToken): array
    {
        $me = self::call('GET', 'me', ['access_token' => $userToken, 'fields' => 'id,name']);
        $pages = self::call('GET', 'me/accounts', ['access_token' => $userToken, 'fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 50]);
        $out = [];
        foreach ($pages['data'] ?? [] as $p) {
            $out[] = [
                'page_id' => (string) $p['id'], 'page_name' => (string) ($p['name'] ?? ''), 'page_token' => (string) ($p['access_token'] ?? ''),
                'ig_id' => isset($p['instagram_business_account']['id']) ? (string) $p['instagram_business_account']['id'] : null,
                'ig_username' => $p['instagram_business_account']['username'] ?? null,
            ];
        }
        return ['user' => $me, 'pages' => $out];
    }

    /** Troca um token curto por um de longa duração (~60 dias). Precisa do App ID/Secret do app da Meta da pessoa. */
    public static function longLived(string $appId, string $appSecret, string $shortToken): array
    {
        $r = self::call('GET', 'oauth/access_token', ['grant_type' => 'fb_exchange_token', 'client_id' => $appId, 'client_secret' => $appSecret, 'fb_exchange_token' => $shortToken]);
        return ['token' => (string) $r['access_token'], 'expires_in' => (int) ($r['expires_in'] ?? 0)];
    }

    /** Publica uma imagem (URL pública) no Instagram profissional. Devolve [id, permalink]. */
    public static function publishInstagram(string $igUserId, string $token, string $imageUrl, string $caption): array
    {
        $c = self::call('POST', $igUserId . '/media', ['access_token' => $token, 'image_url' => $imageUrl, 'caption' => $caption]);
        $creation = (string) ($c['id'] ?? '');
        if ($creation === '') {
            throw new RuntimeException('A Meta não devolveu o contêiner da mídia.');
        }
        // Aguarda o processamento do contêiner (até ~20 s)
        for ($i = 0; $i < 10; $i++) {
            $st = self::call('GET', $creation, ['access_token' => $token, 'fields' => 'status_code']);
            if (($st['status_code'] ?? '') === 'FINISHED') {
                break;
            }
            if (($st['status_code'] ?? '') === 'ERROR') {
                throw new RuntimeException('A Meta não conseguiu processar a imagem (formato ou tamanho). Use JPEG até 8 MB e proporção entre 4:5 e 1.91:1.');
            }
            sleep(2);
        }
        $p = self::call('POST', $igUserId . '/media_publish', ['access_token' => $token, 'creation_id' => $creation]);
        $id = (string) ($p['id'] ?? '');
        $permalink = null;
        try {
            $permalink = self::call('GET', $id, ['access_token' => $token, 'fields' => 'permalink'])['permalink'] ?? null;
        } catch (Throwable) {
        }
        return ['id' => $id, 'permalink' => $permalink];
    }

    /** Publica uma foto na Página do Facebook. */
    public static function publishFacebook(string $pageId, string $pageToken, string $imageUrl, string $caption): array
    {
        $r = self::call('POST', $pageId . '/photos', ['access_token' => $pageToken, 'url' => $imageUrl, 'message' => $caption]);
        $postId = (string) ($r['post_id'] ?? $r['id'] ?? '');
        return ['id' => $postId, 'permalink' => $postId !== '' ? 'https://www.facebook.com/' . $postId : null];
    }
}
