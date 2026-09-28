<?php
// app/services/Zipper.php — download de vários arquivos em ZIP (se ZipArchive existir)
declare(strict_types=1);

final class Zipper
{
    public const MAX_TOTAL_BYTES = 1024 * 1024 * 1024; // 1 GB por ZIP

    public static function available(): bool
    {
        return class_exists('ZipArchive');
    }

    /**
     * Cria o ZIP em pasta temporária e envia ao navegador. Nomes repetidos ganham sufixo.
     * @param array $files linhas de files (já filtradas por permissão)
     */
    public static function send(array $files, string $zipName): never
    {
        if (!self::available()) {
            abort(500, 'Download em ZIP indisponível neste servidor (extensão zip ausente).');
        }
        if (!$files) {
            abort(404, 'Nenhum arquivo para compactar.');
        }
        $total = array_sum(array_map(static fn($f) => (int) $f['size_bytes'], $files));
        if ($total > self::MAX_TOTAL_BYTES) {
            abort(422, 'A seleção passa de ' . format_bytes(self::MAX_TOTAL_BYTES) . '. Baixe em partes menores.');
        }

        $driver = Storage::driver();
        $tmp = tempnam(Storage::tmpDir(), 'zip_');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível criar o arquivo ZIP.');
        }
        $used = [];
        foreach ($files as $f) {
            // Não-admin recebe a versão exibida (sem EXIF) quando existir
            $ref = (!Auth::can('files.original') && $f['display_ref']) ? $f['display_ref'] : $f['storage_ref'];
            $path = $driver->localPath($ref);
            if (!$path || !is_file($path)) {
                continue;
            }
            $name = self::uniqueName($f['original_name'], $used);
            $zip->addFile($path, $name);
            $zip->setCompressionName($name, ZipArchive::CM_STORE); // mídia já é comprimida; só empacota
            DownloadLog::record((int) $f['id'], 'zip');
        }
        $zip->close();
        register_shutdown_function(static fn() => @unlink($tmp));
        Delivery::send($tmp, 'application/zip', $zipName, false);
    }

    private static function uniqueName(string $name, array &$used): string
    {
        $name = str_replace(['/', '\\'], '-', $name);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        $i = 2;
        while (isset($used[mb_strtolower($candidate)])) {
            $candidate = $base . " ({$i})" . ($ext ? '.' . $ext : '');
            $i++;
        }
        $used[mb_strtolower($candidate)] = true;
        return $candidate;
    }
}
