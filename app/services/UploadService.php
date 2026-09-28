<?php
// app/services/UploadService.php — upload em pedaços: iniciar, receber, montar, validar e gravar
declare(strict_types=1);

final class UploadService
{
    /**
     * Cria a sessão de upload.
     * $owner: ['user_id' => int] ou ['guest_upload_id' => int]
     * @throws InvalidArgumentException com mensagem amigável
     */
    public static function start(array $owner, string $name, int $size, ?int $folderId, array $meta = []): array
    {
        $name = trim(mb_substr($name, 0, 255));
        $ext = FileTypes::extension($name);
        if ($name === '' || !FileTypes::isAllowedExtension($ext)) {
            throw new InvalidArgumentException('Tipo de arquivo não permitido (' . ($ext ?: 'sem extensão') . '). Aceitos: ' . implode(', ', FileTypes::allowedExtensions()) . '.');
        }
        if ($size <= 0) {
            throw new InvalidArgumentException('Arquivo vazio.');
        }
        $isGuest = isset($owner['guest_upload_id']);
        $max = $isGuest ? min(GUEST_MAX_FILE_BYTES, UPLOAD_MAX_BYTES) : UPLOAD_MAX_BYTES;
        if ($size > $max) {
            throw new InvalidArgumentException('O arquivo excede o tamanho máximo de ' . format_bytes($max) . '.');
        }

        if ($isGuest) {
            $ip = client_ip();
            if (GuestUpload::filesLastHour($ip) >= GUEST_MAX_FILES_PER_HOUR) {
                throw new InvalidArgumentException('Limite de arquivos por hora atingido. Tente novamente mais tarde.');
            }
            if (GuestUpload::bytesLastDay($ip) + $size > GUEST_MAX_MB_PER_DAY * 1024 * 1024) {
                throw new InvalidArgumentException('Limite diário de envio (' . GUEST_MAX_MB_PER_DAY . ' MB) atingido para esta conexão.');
            }
            $folderId = null;
        } else {
            $userId = (int) $owner['user_id'];
            if ($folderId !== null && !Access::canUploadTo($folderId)) {
                throw new InvalidArgumentException('Você não pode enviar arquivos para esta pasta.');
            }
            $quota = Access::quotaBytes();
            if ($quota > 0 && Access::usedBytes($userId) + UploadSession::pendingBytes($userId) + $size > $quota) {
                throw new InvalidArgumentException('Sua cota de armazenamento (' . format_bytes($quota) . ') seria ultrapassada. Fale com a liderança da mídia.');
            }
        }

        $chunk = UPLOAD_CHUNK_BYTES;
        $id = UploadSession::create([
            'user_id'         => $owner['user_id'] ?? null,
            'guest_upload_id' => $owner['guest_upload_id'] ?? null,
            'folder_id'       => $folderId,
            'original_name'   => $name,
            'size_bytes'      => $size,
            'chunk_size'      => $chunk,
            'chunks_total'    => (int) ceil($size / $chunk),
            'meta'            => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
        @mkdir(self::dir($id), 0750, true);
        return ['upload_id' => $id, 'chunk_size' => $chunk, 'chunks_total' => (int) ceil($size / $chunk), 'received' => []];
    }

    /** Sessão pertence a quem está chamando? */
    public static function owned(array $session): bool
    {
        if ($session['user_id'] !== null) {
            return Auth::id() === (int) $session['user_id'];
        }
        return $session['guest_upload_id'] !== null && (int) ($_SESSION['guest_upload_id'] ?? 0) === (int) $session['guest_upload_id'];
    }

    public static function received(array $session): array
    {
        $dir = self::dir($session['id']);
        $out = [];
        foreach (glob($dir . '/*.part') ?: [] as $f) {
            $out[] = (int) basename($f, '.part');
        }
        sort($out);
        return $out;
    }

    public static function receiveChunk(array $session, int $index, array $upload): void
    {
        if ($session['status'] !== 'aberto') {
            throw new InvalidArgumentException('Esta sessão de upload já foi finalizada.');
        }
        if ($index < 0 || $index >= (int) $session['chunks_total']) {
            throw new InvalidArgumentException('Índice de pedaço inválido.');
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('Pedaço não recebido. Tente novamente.');
        }
        $expected = $index === (int) $session['chunks_total'] - 1
            ? (int) $session['size_bytes'] - $index * (int) $session['chunk_size']
            : (int) $session['chunk_size'];
        if ((int) $upload['size'] !== $expected) {
            throw new InvalidArgumentException('Tamanho do pedaço inesperado.');
        }
        $dir = self::dir($session['id']);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!move_uploaded_file($upload['tmp_name'], $dir . '/' . $index . '.part')) {
            throw new RuntimeException('Falha ao gravar o pedaço no servidor.');
        }
    }

    /**
     * Monta o arquivo, valida e conclui. Devolve ['file' => ...] ou ['duplicate' => ...]
     * quando já existe arquivo idêntico e $keepDuplicate é false.
     */
    public static function finish(array $session, array $meta, bool $keepDuplicate = false): array
    {
        $dir = self::dir($session['id']);
        $assembled = $dir . '/assembled.bin';

        if ($session['status'] === 'aberto') {
            $total = (int) $session['chunks_total'];
            if (count(self::received($session)) !== $total) {
                throw new InvalidArgumentException('Ainda faltam pedaços do arquivo.');
            }
            $out = fopen($assembled, 'wb');
            if (!$out) {
                throw new RuntimeException('Não foi possível montar o arquivo.');
            }
            for ($i = 0; $i < $total; $i++) {
                $in = fopen($dir . '/' . $i . '.part', 'rb');
                if (!$in) {
                    fclose($out);
                    throw new RuntimeException("Pedaço {$i} não encontrado.");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            fclose($out);
            for ($i = 0; $i < $total; $i++) {
                @unlink($dir . '/' . $i . '.part');
            }
            if ((int) filesize($assembled) !== (int) $session['size_bytes']) {
                self::cancel($session);
                throw new InvalidArgumentException('O arquivo montado não confere com o tamanho informado. Envie novamente.');
            }

            $ext = FileTypes::extension($session['original_name']);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($assembled) ?: 'application/octet-stream';
            if (!FileTypes::isCompatible($ext, $mime)) {
                self::cancel($session);
                Logger::info('Upload rejeitado por MIME', ['name' => $session['original_name'], 'mime' => $mime]);
                throw new InvalidArgumentException("O conteúdo do arquivo ({$mime}) não corresponde à extensão .{$ext}.");
            }
            $sha = hash_file('sha256', $assembled);
            UploadSession::setStatus($session['id'], 'montado', ['assembled_path' => $assembled, 'sha256' => $sha, 'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
            $session['status'] = 'montado';
            $session['sha256'] = $sha;
        }

        if (!is_file($assembled)) {
            throw new InvalidArgumentException('Sessão de upload expirada. Envie o arquivo novamente.');
        }

        // Duplicado?
        if (!$keepDuplicate) {
            $dup = MediaFile::findBySha($session['sha256']);
            if ($dup) {
                UploadSession::setStatus($session['id'], 'duplicado');
                return ['duplicate' => [
                    'id' => (int) $dup['id'], 'name' => $dup['original_name'], 'folder' => Folder::pathLabel($dup['folder_id'] ? (int) $dup['folder_id'] : null),
                    'status' => MediaFile::STATUSES[$dup['status']] ?? $dup['status'], 'created_at' => format_datetime($dup['created_at']),
                    'can_view' => Access::canViewFile($dup),
                ]];
            }
        }

        return ['file' => self::store($session, $meta, $assembled)];
    }

    private static function store(array $session, array $meta, string $assembled): array
    {
        $driver = Storage::driver();
        $ext = FileTypes::extension($session['original_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($assembled) ?: 'application/octet-stream';
        $isGuest = $session['guest_upload_id'] !== null;

        $width = $height = null;
        $thumbTmp = $displayTmp = null;
        if (FileTypes::isImage($ext)) {
            $dim = Thumbnails::dimensions($assembled);
            $width = $dim['width'] ?? null;
            $height = $dim['height'] ?? null;
            $thumbTmp = Thumbnails::fromImage($assembled, $ext);
            $displayTmp = Thumbnails::displayVersion($assembled, $ext);
        } elseif (!empty($meta['thumbnail'])) {
            $thumbTmp = Thumbnails::fromDataUrl((string) $meta['thumbnail']);
            $width = isset($meta['width']) ? (int) $meta['width'] ?: null : null;
            $height = isset($meta['height']) ? (int) $meta['height'] ?: null : null;
        }
        $duration = isset($meta['duration']) && is_numeric($meta['duration']) ? (int) round((float) $meta['duration']) : null;

        // Quem envia diretamente para uma pasta permitida: aprovado; caso contrário, quarentena
        $folderId = $session['folder_id'] ? (int) $session['folder_id'] : null;
        $direct = !$isGuest && $folderId !== null && Access::canUploadTo($folderId);
        $status = $direct ? 'aprovado' : 'quarentena';
        if (!$direct) {
            $folderId = null;
        }

        $storageRef = $driver->store($assembled, $direct ? 'files' : 'quarantine', $ext);
        $thumbRef = $thumbTmp ? $driver->store($thumbTmp, 'thumbs', 'jpg') : null;
        $displayRef = $displayTmp ? $driver->store($displayTmp, 'display', 'jpg') : null;

        $category = FileTypes::defaultCategory($ext, $mime);
        if (!$isGuest && isset(FileTypes::CATEGORIES[$meta['category'] ?? ''])) {
            $category = $meta['category'];
        }
        $visibility = null;
        if ($direct && isset(Folder::VISIBILITIES[$meta['visibility'] ?? '']) && Auth::can('folders.manage')) {
            $visibility = $meta['visibility'];
        }

        $guest = $isGuest ? GuestUpload::find((int) $session['guest_upload_id']) : null;

        $fileId = MediaFile::create([
            'folder_id'        => $folderId,
            'driver'           => $driver->name(),
            'storage_ref'      => $storageRef,
            'display_ref'      => $displayRef,
            'thumb_ref'        => $thumbRef,
            'original_name'    => $session['original_name'],
            'extension'        => $ext,
            'mime'             => $mime,
            'size_bytes'       => (int) $session['size_bytes'],
            'sha256'           => $session['sha256'],
            'width'            => $width,
            'height'           => $height,
            'duration_seconds' => $duration,
            'category'         => $category,
            'title'            => mb_substr(trim((string) ($meta['title'] ?? '')), 0, 200) ?: null,
            'description'      => mb_substr(trim((string) ($meta['description'] ?? ($guest['description'] ?? ''))), 0, 2000) ?: null,
            'visibility'       => $visibility,
            'status'           => $status,
            'event_name'       => mb_substr(trim((string) ($meta['event'] ?? ($guest['event_name'] ?? ''))), 0, 150) ?: null,
            'uploaded_by'      => $session['user_id'] !== null ? (int) $session['user_id'] : null,
            'guest_upload_id'  => $session['guest_upload_id'] !== null ? (int) $session['guest_upload_id'] : null,
            'upload_ip'        => client_ip(),
        ]);
        if (!$isGuest && !empty($meta['tags'])) {
            Tag::sync($fileId, (string) $meta['tags']);
        }
        UploadSession::setStatus($session['id'], 'concluido', ['file_id' => $fileId, 'assembled_path' => null]);
        self::removeDir(self::dir($session['id']));

        Logger::audit('arquivo_enviado', 'files', $fileId, null, [
            'nome' => $session['original_name'], 'tamanho' => (int) $session['size_bytes'], 'status' => $status, 'pasta' => $folderId,
            'convidado' => $guest['guest_name'] ?? null,
        ], $session['user_id'] !== null ? (int) $session['user_id'] : null);

        $file = MediaFile::find($fileId);
        return [
            'id' => $fileId, 'name' => $file['original_name'], 'status' => $status, 'status_label' => MediaFile::STATUSES[$status],
            'url' => $isGuest ? null : url('/arquivos/' . $fileId), 'thumb' => $thumbRef && !$isGuest ? url('/arquivos/' . $fileId . '/miniatura') : null,
        ];
    }

    public static function cancel(array $session): void
    {
        self::removeDir(self::dir($session['id']));
        UploadSession::setStatus($session['id'], 'cancelado', ['assembled_path' => null]);
    }

    /** Remove sessões expiradas e seus arquivos temporários (cron). */
    public static function cleanupExpired(): int
    {
        $n = 0;
        foreach (UploadSession::expired() as $s) {
            self::removeDir(self::dir($s['id']));
            UploadSession::delete($s['id']);
            $n++;
        }
        return $n;
    }

    private static function dir(string $id): string
    {
        return Storage::tmpDir() . '/' . preg_replace('/[^a-f0-9]/', '', $id);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
