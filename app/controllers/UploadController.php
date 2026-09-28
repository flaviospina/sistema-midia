<?php
// app/controllers/UploadController.php — API JSON do upload em pedaços (usuários logados e convidados)
declare(strict_types=1);

final class UploadController
{
    /** Identifica quem envia: usuário logado ou convidado com sessão de /enviar. */
    private function owner(): array
    {
        if (Auth::check()) {
            $u = Auth::user();
            if ((int) $u['must_change_password'] === 1 || !Consent::hasActive((int) $u['id'], 'cadastro', TERMS_VERSION)) {
                json_response(false, null, 'Conclua a troca de senha e o aceite do termo antes de enviar arquivos.', 403);
            }
            if (!Auth::can('files.upload')) {
                json_response(false, null, 'Seu perfil não pode enviar arquivos.', 403);
            }
            return ['user_id' => (int) $u['id']];
        }
        $gid = (int) ($_SESSION['guest_upload_id'] ?? 0);
        if ($gid > 0 && GuestUpload::find($gid)) {
            return ['guest_upload_id' => $gid];
        }
        json_response(false, null, 'Sessão de envio não encontrada. Preencha o formulário novamente.', 401);
    }

    private function session(string $id): array
    {
        $s = UploadSession::find($id);
        if (!$s || !UploadService::owned($s)) {
            json_response(false, null, 'Sessão de upload inválida.', 404);
        }
        return $s;
    }

    public function start(): never
    {
        $owner = $this->owner();
        $in = json_input();
        $meta = [
            'title' => (string) ($in['title'] ?? ''), 'description' => (string) ($in['description'] ?? ''), 'tags' => (string) ($in['tags'] ?? ''),
            'event' => (string) ($in['event'] ?? ''), 'category' => (string) ($in['category'] ?? ''), 'visibility' => (string) ($in['visibility'] ?? ''),
            'event_id' => (string) ($in['event_id'] ?? ''),
            'art_request_id' => (string) ($in['art_request_id'] ?? ''), 'art_kind' => (string) ($in['art_kind'] ?? ''),
        ];
        $folderId = isset($in['folder_id']) && ctype_digit((string) $in['folder_id']) ? (int) $in['folder_id'] : null;
        try {
            $r = UploadService::start($owner, (string) ($in['name'] ?? ''), (int) ($in['size'] ?? 0), $folderId, $meta);
        } catch (InvalidArgumentException $e) {
            json_response(false, null, $e->getMessage(), 422);
        }
        json_response(true, $r);
    }

    public function chunk(): never
    {
        $this->owner();
        $s = $this->session((string) ($_POST['upload_id'] ?? ''));
        $index = ctype_digit((string) ($_POST['index'] ?? '')) ? (int) $_POST['index'] : -1;
        try {
            UploadService::receiveChunk($s, $index, $_FILES['chunk'] ?? []);
        } catch (InvalidArgumentException $e) {
            json_response(false, null, $e->getMessage(), 422);
        }
        json_response(true, ['index' => $index, 'received' => count(UploadService::received($s))]);
    }

    public function status(string $id): never
    {
        $this->owner();
        $s = $this->session($id);
        json_response(true, [
            'upload_id' => $s['id'], 'status' => $s['status'], 'chunk_size' => (int) $s['chunk_size'],
            'chunks_total' => (int) $s['chunks_total'], 'received' => $s['status'] === 'aberto' ? UploadService::received($s) : [],
            'file_id' => $s['file_id'] ? (int) $s['file_id'] : null,
        ]);
    }

    public function finish(): never
    {
        $this->owner();
        $in = json_input();
        $s = $this->session((string) ($in['upload_id'] ?? ''));
        if ($s['status'] === 'concluido') {
            json_response(true, ['file' => ['id' => (int) $s['file_id'], 'status' => 'concluido']]);
        }
        if ($s['status'] === 'cancelado') {
            json_response(false, null, 'Este envio foi cancelado.', 410);
        }
        $stored = json_decode((string) $s['meta'], true) ?: [];
        $meta = $stored + [
            'thumbnail' => (string) ($in['thumbnail'] ?? ''), 'duration' => $in['duration'] ?? null,
            'width' => $in['width'] ?? null, 'height' => $in['height'] ?? null,
        ];
        foreach (['title', 'description', 'tags', 'event', 'category', 'visibility', 'event_id', 'art_request_id', 'art_kind'] as $k) {
            if (isset($in[$k]) && is_string($in[$k])) {
                $meta[$k] = $in[$k];
            }
        }
        try {
            $r = UploadService::finish($s, $meta, !empty($in['keep_duplicate']));
        } catch (InvalidArgumentException $e) {
            json_response(false, null, $e->getMessage(), 422);
        }
        json_response(true, $r);
    }

    public function cancel(): never
    {
        $this->owner();
        $in = json_input();
        $s = $this->session((string) ($in['upload_id'] ?? ''));
        if ($s['status'] !== 'concluido') {
            UploadService::cancel($s);
        }
        json_response(true, null, 'Envio cancelado.');
    }
}
