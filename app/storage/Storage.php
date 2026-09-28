<?php
// app/storage/Storage.php — fábrica do driver configurado no .env
declare(strict_types=1);

final class Storage
{
    private static ?StorageDriver $driver = null;

    public static function driver(): StorageDriver
    {
        if (self::$driver === null) {
            self::$driver = match (STORAGE_DRIVER) {
                // 'gdrive' => new GoogleDriveDriver(), // Fase 5
                default => new LocalDriver(),
            };
        }
        return self::$driver;
    }

    /** Pasta temporária para montagem de uploads (sempre local). */
    public static function tmpDir(): string
    {
        $dir = STORAGE_PATH . '/tmp_chunks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }
}
