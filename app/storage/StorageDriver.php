<?php
// app/storage/StorageDriver.php — contrato de armazenamento de arquivos
declare(strict_types=1);

interface StorageDriver
{
    /** Nome curto gravado em files.driver. */
    public function name(): string;

    /**
     * Move um arquivo local para o armazenamento definitivo.
     * @param string $localPath caminho do arquivo montado (tmp)
     * @param string $prefix    subpasta lógica: files | thumbs | display
     * @param string $extension extensão sem ponto
     * @return string referência a gravar em files.*_ref
     */
    public function store(string $localPath, string $prefix, string $extension): string;

    /** Caminho local legível (para readfile/fopen). Null se o driver não expõe arquivo local. */
    public function localPath(string $ref): ?string;

    public function exists(string $ref): bool;

    public function size(string $ref): int;

    public function delete(string $ref): void;

    /** Bytes ocupados por subpasta lógica (files, thumbs, display). */
    public function usage(): array;
}
