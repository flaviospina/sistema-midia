<?php
// app/models/Upload.php — sessões de envio em pedaços (retomada após queda de conexão)
declare(strict_types=1);

final class Upload
{
    public static function find(string $id): ?array
    {
        return preg_match('/^[a-f0-9]{32}$/', $id) ? Database::one('SELECT * FROM photo_uploads WHERE id = :id', ['id' => $id]) : null;
    }

    public static function start(int $albumId, string $name, int $size): array
    {
        $id = bin2hex(random_bytes(16));
        $total = max(1, (int) ceil($size / UPLOAD_CHUNK_BYTES));
        Database::run(
            'INSERT INTO photo_uploads (id, album_id, user_id, original_name, size_bytes, chunk_bytes, chunks_total) VALUES (:id, :a, :u, :n, :s, :c, :t)',
            ['id' => $id, 'a' => $albumId, 'u' => Auth::id(), 'n' => mb_substr($name, 0, 255), 's' => $size, 'c' => UPLOAD_CHUNK_BYTES, 't' => $total]
        );
        @mkdir(self::dir($id), 0750, true);
        return ['upload_id' => $id, 'chunk_bytes' => UPLOAD_CHUNK_BYTES, 'chunks_total' => $total];
    }

    public static function dir(string $id): string
    {
        return TMP_PATH . '/' . $id;
    }

    /** Índices dos pedaços já gravados (para retomar). */
    public static function received(string $id): array
    {
        $out = [];
        foreach (glob(self::dir($id) . '/*.part') ?: [] as $f) {
            $out[] = (int) basename($f, '.part');
        }
        sort($out);
        return $out;
    }

    public static function saveChunk(array $up, int $index, string $tmpFile): void
    {
        if ($index < 0 || $index >= (int) $up['chunks_total']) {
            throw new InvalidArgumentException('Pedaço fora do intervalo.');
        }
        if (!is_dir(self::dir($up['id']))) {
            @mkdir(self::dir($up['id']), 0750, true);
        }
        if (!move_uploaded_file($tmpFile, self::dir($up['id']) . '/' . $index . '.part')) {
            throw new RuntimeException('Não foi possível gravar o pedaço.');
        }
        Database::run('UPDATE photo_uploads SET chunks_received = :r WHERE id = :id', ['r' => count(self::received($up['id'])), 'id' => $up['id']]);
    }

    /** Junta os pedaços num arquivo temporário e devolve o caminho. */
    public static function assemble(array $up): string
    {
        $parts = self::received($up['id']);
        if (count($parts) !== (int) $up['chunks_total']) {
            throw new RuntimeException('Envio incompleto: faltam pedaços. O envio será retomado.');
        }
        $target = self::dir($up['id']) . '/arquivo.bin';
        $out = fopen($target, 'wb');
        foreach ($parts as $i) {
            $in = fopen(self::dir($up['id']) . '/' . $i . '.part', 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        if (filesize($target) !== (int) $up['size_bytes']) {
            self::discard($up['id']);
            throw new RuntimeException('O tamanho recebido não confere. Envie novamente.');
        }
        return $target;
    }

    public static function finish(string $id, string $status = 'concluido'): void
    {
        Database::run('UPDATE photo_uploads SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
        self::discard($id);
    }

    public static function discard(string $id): void
    {
        foreach (glob(self::dir($id) . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir(self::dir($id));
    }

    /** Remove sessões abertas há mais de UPLOAD_SESSION_HOURS (cron). */
    public static function purgeExpired(): int
    {
        $rows = Database::all("SELECT id FROM photo_uploads WHERE status = 'aberto' AND created_at < DATE_SUB(NOW(), INTERVAL :h HOUR)", ['h' => UPLOAD_SESSION_HOURS]);
        foreach ($rows as $r) {
            self::finish($r['id'], 'cancelado');
        }
        return count($rows);
    }
}
