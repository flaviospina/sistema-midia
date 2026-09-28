<?php
// app/controllers/N8nController.php — endpoints chamados PELO n8n (respostas do WhatsApp e teste de conexão)
declare(strict_types=1);

final class N8nController
{
    /** Valida o segredo do header; sem ele, 401 (sem revelar mais nada). */
    private function guard(): void
    {
        $sent = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
        if (N8N_INBOUND_SECRET === '' || !hash_equals(N8N_INBOUND_SECRET, $sent)) {
            Logger::info('Chamada do n8n rejeitada (segredo inválido)');
            json_response(false, null, 'Não autorizado.', 401);
        }
    }

    public function ping(): never
    {
        $this->guard();
        json_response(true, ['app' => APP_CODE, 'version' => APP_VERSION, 'time' => date('c')], 'pong');
    }

    /**
     * Mensagem recebida no WhatsApp: {"phone": "5511999998888", "text": "sim"}.
     * Devolve {"reply": "..."} para o n8n mandar de volta (ou reply vazio para ignorar).
     */
    public function inbound(): never
    {
        $this->guard();
        $in = json_input();
        $phone = Validator::normalizePhone((string) ($in['phone'] ?? ''));
        $text = trim(mb_substr((string) ($in['text'] ?? ''), 0, 500));
        if ($phone === null || $text === '') {
            json_response(false, null, 'Informe phone e text.', 422);
        }
        $user = Database::one("SELECT u.id, u.name FROM users u WHERE u.whatsapp = :p AND u.status = 'ativo' LIMIT 1", ['p' => $phone]);
        [$action, $reply] = $user ? $this->handle($user, $text) : ['desconhecido', ''];

        Database::run(
            'INSERT INTO inbound_messages (phone, user_id, text, action, reply) VALUES (:p, :u, :t, :a, :r)',
            ['p' => $phone, 'u' => $user['id'] ?? null, 't' => $text, 'a' => $action, 'r' => $reply ?: null]
        );
        json_response(true, ['action' => $action, 'reply' => $reply, 'user_id' => $user['id'] ?? null]);
    }

    /** Interpreta SIM/NÃO para a próxima escala pendente da pessoa. */
    private function handle(array $user, string $text): array
    {
        $t = mb_strtolower(trim($text));
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
        $t = preg_replace('/[^a-z0-9 ]/', '', $t) ?? $t;
        $yes = (bool) preg_match('/^(sim|s|confirmo|confirmar|confirmado|ok|pode contar|vou)\b/', $t);
        $no = (bool) preg_match('/^(nao|n|recuso|recusar|nao posso|nao vou|nao consigo)\b/', $t);
        if (!$yes && !$no) {
            return ['ignorado', ''];
        }
        $pending = Assignment::pendingForUser((int) $user['id']);
        if (!$pending) {
            return ['sem_pendencia', "Olá, {$user['name']}! Você não tem escala aguardando confirmação. Veja sua agenda em " . absolute_url('/minha-escala')];
        }
        $a = $pending[0];
        if ($yes) {
            Assignment::respond((int) $a['id'], 'confirmado', 'Confirmado pelo WhatsApp');
            Logger::audit('escala_confirmada', 'assignments', $a['id'], ['status' => 'pendente'], ['status' => 'confirmado', 'via' => 'whatsapp'], (int) $user['id']);
            $rest = count($pending) - 1;
            return ['confirmado', "✅ Confirmado: *{$a['function_name']}* em *{$a['event_title']}* (" . format_date($a['starts_at'], 'd/m H:i') . ')' . ($rest ? ". Você ainda tem {$rest} escala(s) pendente(s); responda SIM de novo para confirmar a próxima." : '.')];
        }
        $note = mb_substr(trim((string) preg_replace('/^(n[aã]o|n)(\s+(posso|vou|consigo))?[\s,.:;-]*/iu', '', $text)), 0, 300) ?: 'Recusado pelo WhatsApp';
        Assignment::respond((int) $a['id'], 'recusado', $note);
        Logger::audit('escala_recusada', 'assignments', $a['id'], ['status' => 'pendente'], ['status' => 'recusado', 'via' => 'whatsapp', 'motivo' => $note], (int) $user['id']);
        Notifier::declined(Assignment::find((int) $a['id']), $note);
        return ['recusado', "Registrado: você recusou *{$a['function_name']}* em *{$a['event_title']}* (" . format_date($a['starts_at'], 'd/m H:i') . '). O coordenador foi avisado. Se puder, envie o motivo respondendo "NÃO motivo".'];
    }
}
