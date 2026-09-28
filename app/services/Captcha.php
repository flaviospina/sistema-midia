<?php
// app/services/Captcha.php — hCaptcha opcional na página pública (via cURL)
declare(strict_types=1);

final class Captcha
{
    public static function enabled(): bool
    {
        return HCAPTCHA_ENABLED;
    }

    public static function verify(string $response): bool
    {
        if (!self::enabled()) {
            return true;
        }
        if ($response === '' || !function_exists('curl_init')) {
            return false;
        }
        $ch = curl_init('https://api.hcaptcha.com/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['secret' => HCAPTCHA_SECRET, 'response' => $response, 'remoteip' => client_ip()]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            Logger::error('hCaptcha indisponível: ' . $err);
            return false;
        }
        $data = json_decode((string) $body, true);
        return (bool) ($data['success'] ?? false);
    }
}
