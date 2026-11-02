<?php
// app/models/PublicLink.php — link público temporário de uma foto (a Meta baixa a imagem por URL)
declare(strict_types=1);

final class PublicLink
{
    public static function issue(int $photoId, int $minutes = PUBLIC_LINK_MINUTES): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        Database::run('INSERT INTO photo_public_links (photo_id, token_hash, expires_at, created_by) VALUES (:p, :h, DATE_ADD(NOW(), INTERVAL :m MINUTE), :u)', ['p' => $photoId, 'h' => hash('sha256', $token), 'm' => $minutes, 'u' => Auth::id()]);
        return $token;
    }

    public static function resolve(string $token): ?array
    {
        return Database::one('SELECT l.*, p.id AS pid FROM photo_public_links l JOIN photos p ON p.id = l.photo_id WHERE l.token_hash = :h AND l.expires_at > NOW()', ['h' => hash('sha256', $token)]);
    }

    public static function purge(): void
    {
        Database::run('DELETE FROM photo_public_links WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}
