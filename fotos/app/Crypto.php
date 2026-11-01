<?php
// app/Crypto.php — cifra/decifra segredos (tokens de redes sociais) com AES-256-GCM e a chave do .env
declare(strict_types=1);

final class Crypto
{
    public static function ready(): bool
    {
        return APP_KEY !== '' && preg_match('/^[a-f0-9]{64}$/i', APP_KEY) === 1;
    }

    public static function encrypt(string $plain): string
    {
        if (!self::ready()) {
            throw new RuntimeException('APP_KEY não configurada no .env (64 caracteres hexadecimais).');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', hex2bin(APP_KEY), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Falha ao cifrar.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): ?string
    {
        if (!self::ready()) {
            return null;
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hex2bin(APP_KEY), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}
