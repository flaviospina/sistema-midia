<?php
// app/storage/LocalDriver.php — armazenamento em disco (STORAGE_PATH), fora da raiz pública
declare(strict_types=1);

final class LocalDriver implements StorageDriver
{
    public function __construct(private readonly string $root = STORAGE_PATH)
    {
    }

    public function name(): string
    {
        return 'local';
    }

    public function store(string $localPath, string $prefix, string $extension): string
    {
        $prefix = self::safePrefix($prefix);
        $ext = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $extension));
        $rel = $prefix . '/' . date('Y') . '/' . date('m');
        $dir = $this->root . '/' . $rel;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar a pasta de armazenamento {$rel}.");
        }
        do {
            $name = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
        } while (file_exists($dir . '/' . $name));

        if (!@rename($localPath, $dir . '/' . $name)) {
            // rename pode falhar entre partições; cai para copiar + apagar
            if (!@copy($localPath, $dir . '/' . $name)) {
                throw new RuntimeException('Falha ao gravar o arquivo no armazenamento.');
            }
            @unlink($localPath);
        }
        @chmod($dir . '/' . $name, 0640);
        return $rel . '/' . $name;
    }

    public function localPath(string $ref): ?string
    {
        if (!self::validRef($ref)) {
            return null;
        }
        return $this->root . '/' . $ref;
    }

    public function exists(string $ref): bool
    {
        $p = $this->localPath($ref);
        return $p !== null && is_file($p);
    }

    public function size(string $ref): int
    {
        $p = $this->localPath($ref);
        return $p !== null && is_file($p) ? (int) filesize($p) : 0;
    }

    public function delete(string $ref): void
    {
        $p = $this->localPath($ref);
        if ($p !== null && is_file($p)) {
            @unlink($p);
        }
    }

    public function usage(): array
    {
        $out = [];
        foreach (['files', 'thumbs', 'display', 'tmp_chunks', 'quarantine'] as $prefix) {
            $out[$prefix] = self::dirSize($this->root . '/' . $prefix);
        }
        return $out;
    }

    private static function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $total = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $total += $f->getSize();
        }
        return $total;
    }

    private static function safePrefix(string $prefix): string
    {
        return in_array($prefix, ['files', 'thumbs', 'display', 'quarantine'], true) ? $prefix : 'files';
    }

    /** Impede path traversal: só letras, números, "/", "." e "_" e nunca "..". */
    private static function validRef(string $ref): bool
    {
        return $ref !== '' && !str_contains($ref, '..') && (bool) preg_match('#^[a-z0-9_]+(/[a-z0-9_.-]+)+$#i', $ref);
    }
}
