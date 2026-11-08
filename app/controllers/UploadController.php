<?php
// app/controllers/UploadController.php — envio de fotos em pedaços com retomada; valida, grava na pasta do álbum e gera derivados
declare(strict_types=1);

final class UploadController
{
    public function page(int $albumId): never
    {
        $a = Album::find($albumId) ?? abort(404);
        view('albums/upload', ['title' => 'Enviar fotos · ' . $a['name'], 'a' => $a, 'chunkBytes' => UPLOAD_CHUNK_BYTES, 'maxBytes' => UPLOAD_MAX_BYTES]);
    }

    public function start(): never
    {
        $in = json_input();
        $albumId = (int) ($in['album_id'] ?? 0);
        $name = trim((string) ($in['name'] ?? ''));
        $size = (int) ($in['size'] ?? 0);
        $a = Album::find($albumId);
        if (!$a) {
            json_response(false, null, 'Pasta não encontrada.', 404);
        }
        if ($name === '' || $size <= 0) {
            json_response(false, null, 'Arquivo inválido.', 422);
        }
        if ($size > UPLOAD_MAX_BYTES) {
            json_response(false, null, 'Arquivo maior que o limite de ' . format_bytes(UPLOAD_MAX_BYTES) . '.', 422);
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
            json_response(false, null, 'Só fotos: JPG, PNG, WebP ou HEIC.', 422);
        }
        // Retomada: mesmo nome/tamanho/usuário/álbum ainda aberto
        $open = Database::one("SELECT * FROM em_uploads WHERE album_id = :a AND user_id = :u AND original_name = :n AND size_bytes = :s AND status = 'aberto' ORDER BY created_at DESC LIMIT 1", ['a' => $albumId, 'u' => Auth::id(), 'n' => mb_substr($name, 0, 255), 's' => $size]);
        if ($open) {
            json_response(true, ['upload_id' => $open['id'], 'chunk_bytes' => (int) $open['chunk_bytes'], 'chunks_total' => (int) $open['chunks_total'], 'received' => Upload::received($open['id'])]);
        }
        json_response(true, Upload::start($albumId, $name, $size) + ['received' => []]);
    }

    public function chunk(): never
    {
        $up = Upload::find(input('upload_id'));
        if (!$up || $up['status'] !== 'aberto' || (int) $up['user_id'] !== Auth::id()) {
            json_response(false, null, 'Envio não encontrado ou já concluído.', 404);
        }
        $f = $_FILES['chunk'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            json_response(false, null, 'Pedaço não recebido. Tentando de novo…', 422);
        }
        if ((int) $f['size'] > (int) $up['chunk_bytes']) {
            json_response(false, null, 'Pedaço maior que o permitido.', 422);
        }
        try {
            Upload::saveChunk($up, (int) input('index', '-1'), $f['tmp_name']);
        } catch (Throwable $e) {
            json_response(false, null, $e->getMessage(), 422);
        }
        json_response(true, ['received' => Upload::received($up['id'])]);
    }

    public function finish(): never
    {
        $in = json_input();
        $up = Upload::find((string) ($in['upload_id'] ?? ''));
        if (!$up || $up['status'] !== 'aberto' || (int) $up['user_id'] !== Auth::id()) {
            json_response(false, null, 'Envio não encontrado ou já concluído.', 404);
        }
        $a = Album::find((int) $up['album_id']);
        if (!$a) {
            json_response(false, null, 'Pasta não encontrada.', 404);
        }
        try {
            $file = Upload::assemble($up);
            $r = PhotoIngest::store($a, $file, $up['original_name'], ['keep_duplicate' => !empty($in['keep_duplicate'])]);
            if (isset($r['duplicate'])) {
                json_response(true, ['duplicate' => $r['duplicate']]);
            }
            Upload::finish($up['id']);
            Logger::audit('foto_enviada', 'em_photos', $r['photo'], null, ['album_id' => $a['id'], 'file' => $r['file_name']]);
            json_response(true, ['photo' => ['id' => $r['photo'], 'name' => $r['file_name'], 'thumb' => url('/fotos/' . $r['photo'] . '/miniatura'), 'url' => url('/albuns/' . $a['id'], ['foto' => $r['photo']])]]);
        } catch (InvalidArgumentException $e) {
            Upload::finish($up['id'], 'cancelado');
            json_response(false, null, $e->getMessage(), 422);
        } catch (Throwable $e) {
            Logger::error('Falha ao concluir envio: ' . $e->getMessage(), ['upload' => $up['id']]);
            json_response(false, null, APP_ENV === 'development' ? $e->getMessage() : 'Não foi possível concluir o envio. Tente novamente.', 500);
        }
    }

    public function cancel(): never
    {
        $in = json_input();
        $up = Upload::find((string) ($in['upload_id'] ?? ''));
        if ($up && (int) $up['user_id'] === Auth::id()) {
            Upload::finish($up['id'], 'cancelado');
        }
        json_response(true);
    }
}
