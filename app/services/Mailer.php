<?php
// app/services/Mailer.php — envio de e-mail sem biblioteca: mail(), SMTP próprio (SSL/STARTTLS) ou log
declare(strict_types=1);

final class Mailer
{
    /**
     * Envia um e-mail em texto simples. Devolve true/false; nunca lança exceção (erros vão para o log).
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            return match (MAIL_DRIVER) {
                'smtp' => self::viaSmtp($to, $subject, $body),
                'log'  => self::viaLog($to, $subject, $body),
                default => self::viaMail($to, $subject, $body),
            };
        } catch (Throwable $e) {
            Logger::error('Falha ao enviar e-mail: ' . $e->getMessage(), ['para' => $to, 'assunto' => $subject]);
            return false;
        }
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function headers(string $to, string $subject): array
    {
        return [
            'From: ' . self::encodeHeader(MAIL_FROM_NAME) . ' <' . MAIL_FROM . '>',
            'Reply-To: ' . MAIL_FROM,
            'To: ' . $to,
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Central de Midia ADMoema',
        ];
    }

    private static function viaMail(string $to, string $subject, string $body): bool
    {
        $headers = array_filter(self::headers($to, $subject), static fn($h) => !str_starts_with($h, 'To: ') && !str_starts_with($h, 'Subject: '));
        $ok = @mail($to, self::encodeHeader($subject), $body, implode("\r\n", $headers), '-f' . MAIL_FROM);
        if (!$ok) {
            Logger::error('mail() devolveu falso', ['para' => $to]);
        }
        return $ok;
    }

    private static function viaLog(string $to, string $subject, string $body): bool
    {
        Logger::info('E-mail (MAIL_DRIVER=log)', ['para' => $to, 'assunto' => $subject, 'corpo' => $body]);
        return true;
    }

    /** Cliente SMTP mínimo: AUTH LOGIN, SSL direto (465) ou STARTTLS (587). */
    private static function viaSmtp(string $to, string $subject, string $body): bool
    {
        if (SMTP_HOST === '') {
            throw new RuntimeException('SMTP_HOST não configurado no .env.');
        }
        $prefix = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $fp = @stream_socket_client($prefix . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("Conexão SMTP falhou: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 15);
        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $c, string $expect) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!str_starts_with($r, $expect)) {
                throw new RuntimeException('SMTP: resposta inesperada a "' . preg_replace('/^(AUTH|[A-Za-z0-9+\/=]{16,}).*/', '$1 …', $c) . '": ' . trim($r));
            }
            return $r;
        };
        $host = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
        $read();
        $cmd('EHLO ' . $host, '250');
        if (SMTP_ENCRYPTION === 'tls') {
            $cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP: STARTTLS falhou.');
            }
            $cmd('EHLO ' . $host, '250');
        }
        if (SMTP_USER !== '') {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode(SMTP_USER), '334');
            $cmd(base64_encode(SMTP_PASS), '235');
        }
        $cmd('MAIL FROM:<' . MAIL_FROM . '>', '250');
        $cmd('RCPT TO:<' . $to . '>', '250');
        $cmd('DATA', '354');
        $data = implode("\r\n", self::headers($to, $subject)) . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], "\r\n", $body));
        $cmd($data . "\r\n.", '250');
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
