<?php
// app/services/Notifier.php — monta os avisos (texto pronto em pt-BR), enfileira e envia ao n8n via cURL
declare(strict_types=1);

final class Notifier
{
    /**
     * Enfileira um aviso para uma lista de usuários (ids). Só quem tem WhatsApp e não desligou o aviso.
     * Envia imediatamente quando possível; se falhar, o cron reenvia.
     */
    public static function notify(string $event, array $userIds, string $message, array $data = [], ?string $dedupeKey = null, int $delaySeconds = 0): ?int
    {
        if (!Setting::eventEnabled($event)) {
            return null;
        }
        $recipients = self::recipients($userIds);
        if (!$recipients) {
            return null;
        }
        $id = Notification::enqueue($event, $recipients, $message, $data, $dedupeKey, $delaySeconds);
        if ($delaySeconds === 0 && $dedupeKey === null && NOTIFY_ENABLED && N8N_WEBHOOK_URL !== '') {
            self::dispatch(Notification::find($id));
        }
        return $id;
    }

    /** Usuários ativos com WhatsApp cadastrado, exceto quem desligou em "Meus dados". */
    public static function recipients(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return [];
        }
        $optOut = Setting::whatsappOptOutIds();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all("SELECT id, name, whatsapp FROM users WHERE id IN ({$in}) AND status = 'ativo' AND whatsapp IS NOT NULL AND whatsapp <> ''", $ids);
        $out = [];
        foreach ($rows as $r) {
            if (!in_array((int) $r['id'], $optOut, true)) {
                $out[] = ['user_id' => (int) $r['id'], 'name' => $r['name'], 'whatsapp' => $r['whatsapp']];
            }
        }
        return $out;
    }

    /** Envia uma notificação ao n8n. Devolve true se aceita (2xx). */
    public static function dispatch(?array $n): bool
    {
        if (!$n || $n['status'] !== 'pendente') {
            return false;
        }
        if (!NOTIFY_ENABLED || N8N_WEBHOOK_URL === '') {
            Notification::markFailed((int) $n['id'], (int) $n['attempts'], 'N8N_WEBHOOK_URL não configurada ou NOTIFY_ENABLED=0', null);
            return false;
        }
        $payload = [
            'app'        => APP_CODE,
            'event'      => $n['event'],
            'id'         => (int) $n['id'],
            'sent_at'    => date('c'),
            'recipients' => json_decode((string) $n['recipients'], true) ?: [],
            'message'    => $n['message'],
            'data'       => json_decode((string) $n['data'], true) ?: [],
            'base_url'   => BASE_URL,
        ];
        [$code, $body, $err] = self::post(N8N_WEBHOOK_URL, $payload, N8N_WEBHOOK_SECRET);
        if ($code >= 200 && $code < 300) {
            Notification::markSent((int) $n['id'], $code);
            return true;
        }
        Notification::markFailed((int) $n['id'], (int) $n['attempts'], $err ?: ('HTTP ' . $code . ' ' . mb_substr($body, 0, 200)), $code ?: null);
        Logger::error('Falha ao enviar aviso ao n8n', ['id' => $n['id'], 'evento' => $n['event'], 'http' => $code, 'erro' => $err]);
        return false;
    }

    /** POST JSON via cURL (allow_url_fopen pode estar desligado). @return array{int,string,string} */
    public static function post(string $url, array $payload, string $secret): array
    {
        if (!function_exists('curl_init')) {
            return [0, '', 'Extensão cURL indisponível'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'X-Webhook-Secret: ' . $secret, 'User-Agent: CentralMidiaADMoema/' . APP_VERSION],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$code, is_string($body) ? $body : '', $err];
    }

    /** Processa a fila (cron). Devolve [enviados, falhas]. */
    public static function flush(int $limit = 30): array
    {
        $ok = 0;
        $fail = 0;
        foreach (Notification::due($limit) as $n) {
            self::dispatch($n) ? $ok++ : $fail++;
        }
        return [$ok, $fail];
    }

    // ---- Mensagens prontas -------------------------------------------------

    private static function when(string $dt): string
    {
        return Event::WEEKDAYS[(int) date('w', strtotime($dt))] . ', ' . format_date($dt, 'd/m') . ' às ' . substr($dt, 11, 5);
    }

    private static function link(string $path): string
    {
        return BASE_URL . url($path);
    }

    public static function assigned(array $a): void
    {
        $msg = "Olá, {$a['user_name']}! Você foi escalado(a) para *{$a['function_name']}* em *{$a['event_title']}* (" . self::when($a['starts_at']) . ").\n"
            . "Responda *SIM* para confirmar ou *NÃO* para recusar, ou acesse: " . self::link('/minha-escala');
        self::notify('escala.escalado', [(int) $a['user_id']], $msg, ['assignment_id' => (int) $a['id'], 'event_id' => (int) $a['event_id'], 'function' => $a['function_name'], 'starts_at' => $a['starts_at']]);
    }

    public static function declined(array $a, ?string $note): void
    {
        $msg = "⚠️ {$a['user_name']} recusou a escala de *{$a['function_name']}* em *{$a['event_title']}* (" . self::when($a['starts_at']) . ')'
            . ($note ? ".\nMotivo: {$note}" : '.') . "\nEscale outra pessoa: " . self::link('/eventos/' . (int) $a['event_id'] . '/escala');
        self::notify('escala.recusada', self::coordinatorsFor((int) $a['function_id']), $msg, ['assignment_id' => (int) $a['id'], 'event_id' => (int) $a['event_id']]);
    }

    public static function swapRequested(array $s): void
    {
        $msg = "Olá, {$s['to_name']}! {$s['from_name']} pediu para trocar com você a escala de *{$s['function_name']}* em *{$s['event_title']}* (" . self::when($s['starts_at']) . ')'
            . ($s['reason'] ? ".\nMotivo: {$s['reason']}" : '.') . "\nAceite ou recuse em: " . self::link('/minha-escala');
        self::notify('escala.troca', [(int) $s['to_user_id']], $msg, ['swap_id' => (int) $s['id']]);
    }

    public static function swapAccepted(array $s): void
    {
        $msg = "🔁 Troca aguardando sua aprovação: {$s['from_name']} → {$s['to_name']} em *{$s['function_name']}*, *{$s['event_title']}* (" . self::when($s['starts_at']) . ").\nAprovar: " . self::link('/trocas');
        self::notify('escala.troca', self::coordinatorsFor((int) $s['function_id']), $msg, ['swap_id' => (int) $s['id']]);
    }

    public static function swapDecided(array $s, bool $approved): void
    {
        $msg = $approved
            ? "✅ Troca aprovada: {$s['to_name']} assume *{$s['function_name']}* em *{$s['event_title']}* (" . self::when($s['starts_at']) . '). ' . $s['from_name'] . ' está liberado(a).'
            : "❌ A troca de *{$s['function_name']}* em *{$s['event_title']}* (" . self::when($s['starts_at']) . ") não foi aprovada pelo coordenador. A escala original permanece.";
        self::notify('escala.troca', [(int) $s['from_user_id'], (int) $s['to_user_id']], $msg, ['swap_id' => (int) $s['id']]);
    }

    public static function eventCancelled(array $event): void
    {
        $ids = Assignment::userIdsInEvent((int) $event['id']);
        if (!$ids) {
            return;
        }
        $msg = "🚫 O evento *{$event['title']}* (" . self::when($event['starts_at']) . ") foi cancelado. Sua escala nele não vale mais.";
        self::notify('evento.cancelado', $ids, $msg, ['event_id' => (int) $event['id']]);
    }

    public static function quarantine(array $file): void
    {
        $who = $file['guest_name'] ?? ($file['uploader_name'] ?? 'alguém');
        $msg = "📥 Novos arquivos na quarentena aguardando moderação.\nÚltimo: {$file['original_name']} (de {$who}).\nModerar: " . self::link('/moderacao');
        $ids = self::roleIds(['admin', 'coordenador']);
        // Agrupa por 10 minutos para não mandar um aviso por foto
        self::notify('arquivo.quarentena', $ids, $msg, ['items' => [$file['original_name']], 'file_id' => (int) $file['id']], 'quarentena', 600);
    }

    /** Mudança de status de arte: escolhe destinatários pelo novo estado. */
    public static function artStatus(array $r, string $note = ''): void
    {
        $title = $r['title'];
        $link = self::link('/artes/' . (int) $r['id']);
        $status = $r['status'];
        $ids = [];
        $msg = '';
        switch ($status) {
            case 'recebido':
                $ids = self::roleIds(['admin', 'coordenador']);
                $msg = "🎨 Novo pedido de arte: *{$title}* ({$r['ministry_name']}), publicação em " . format_date($r['publish_on']) . ($r['is_urgent'] ? ' — *URGENTE*' : '') . ".\nVer: {$link}";
                break;
            case 'em_producao':
                $ids = [(int) $r['requester_id']];
                $msg = "🎨 Seu pedido *{$title}* entrou em produção" . ($r['designer_name'] ? " com {$r['designer_name']}" : '') . ".\nAcompanhe: {$link}";
                break;
            case 'revisao_solicitante':
                $ids = [(int) $r['requester_id']];
                $msg = "👀 A arte *{$title}* está pronta para sua revisão (versão {$r['current_version']}). Aprove ou peça ajustes: {$link}";
                break;
            case 'ajustes':
                $ids = $r['designer_id'] ? [(int) $r['designer_id']] : self::roleIds(['admin', 'coordenador']);
                $msg = "✏️ Ajustes pedidos em *{$title}*" . ($note ? ": {$note}" : '') . "\nVer: {$link}";
                break;
            case 'aprovacao_midia':
                $ids = self::roleIds(['admin', 'coordenador']);
                $msg = "✅ O solicitante aprovou *{$title}*. Falta o checklist e a aprovação da mídia: {$link}";
                break;
            case 'aprovacao_pastoral':
                $ids = self::roleIds(['pastor', 'admin']);
                $msg = "🙏 A arte *{$title}* aguarda aprovação pastoral: {$link}";
                break;
            case 'aprovado':
                $ids = array_filter([(int) $r['requester_id'], $r['designer_id'] ? (int) $r['designer_id'] : 0]);
                $msg = "🎉 A arte *{$title}* foi aprovada e entrou no calendário de comunicação para " . format_date($r['publish_on']) . ".\nVer: {$link}";
                break;
            case 'publicado':
                $ids = [(int) $r['requester_id']];
                $msg = "📣 *{$title}* foi publicada. Obrigado!";
                break;
            case 'cancelado':
                $ids = array_filter([(int) $r['requester_id'], $r['designer_id'] ? (int) $r['designer_id'] : 0]);
                $msg = "🚫 O pedido *{$title}* foi cancelado" . ($note ? ": {$note}" : '.');
                break;
        }
        if ($ids && $msg) {
            // Quem fez a ação não precisa ser avisado
            $ids = array_values(array_diff(array_map('intval', $ids), [(int) Auth::id()]));
            self::notify('arte.status', $ids, $msg, ['request_id' => (int) $r['id'], 'status' => $status]);
        }
    }

    // ---- Rotinas diárias (cron) ---------------------------------------------

    /** Lembrete para escalas nas próximas 24–48 h (uma mensagem por pessoa). */
    public static function dailyReminders(): int
    {
        $rows = Database::all(
            "SELECT a.id, a.user_id, a.status, u.name AS user_name, f.name AS function_name, e.title AS event_title, e.starts_at, e.location
               FROM assignments a JOIN users u ON u.id = a.user_id JOIN media_functions f ON f.id = a.function_id JOIN events e ON e.id = a.event_id
              WHERE a.status <> 'recusado' AND e.status = 'agendado'
                AND e.starts_at >= DATE_ADD(NOW(), INTERVAL 12 HOUR) AND e.starts_at < DATE_ADD(NOW(), INTERVAL 36 HOUR)
              ORDER BY a.user_id, e.starts_at"
        );
        $byUser = [];
        foreach ($rows as $r) {
            $byUser[(int) $r['user_id']][] = $r;
        }
        $n = 0;
        foreach ($byUser as $uid => $list) {
            $lines = [];
            $pending = false;
            foreach ($list as $r) {
                $lines[] = "• *{$r['event_title']}* — {$r['function_name']} — " . self::when($r['starts_at']) . ($r['location'] ? " ({$r['location']})" : '') . ($r['status'] === 'pendente' ? ' — *ainda não confirmado*' : '');
                $pending = $pending || $r['status'] === 'pendente';
            }
            $msg = "⏰ Olá, {$list[0]['user_name']}! Lembrete da sua escala de amanhã:\n" . implode("\n", $lines)
                . ($pending ? "\nResponda *SIM* para confirmar." : '') . "\n" . self::link('/minha-escala');
            self::notify('escala.lembrete', [$uid], $msg, ['assignment_ids' => array_map(static fn($r) => (int) $r['id'], $list)], 'lembrete:' . $uid . ':' . date('Y-m-d'));
            $n++;
        }
        return $n;
    }

    /** Publicações planejadas para hoje (para o responsável ou, sem responsável, para quem gerencia). */
    public static function dailyPublications(): int
    {
        $rows = Database::all("SELECT p.*, r.title AS request_title FROM publications p LEFT JOIN art_requests r ON r.id = p.request_id WHERE p.status = 'planejado' AND DATE(p.publish_at) = CURDATE() ORDER BY p.publish_at");
        if (!$rows) {
            return 0;
        }
        $byUser = [];
        $managers = self::roleIds(['admin', 'coordenador', 'membro_midia']);
        foreach ($rows as $p) {
            $targets = $p['responsible_id'] ? [(int) $p['responsible_id']] : $managers;
            foreach ($targets as $uid) {
                $byUser[$uid][] = $p;
            }
        }
        foreach ($byUser as $uid => $list) {
            $lines = array_map(static fn($p) => '• ' . substr($p['publish_at'], 11, 5) . ' ' . Publication::CHANNELS[$p['channel']] . ': ' . $p['title'], $list);
            $msg = "📣 Publicações de hoje:\n" . implode("\n", $lines) . "\nMarque como publicado em: " . self::link('/comunicacao');
            self::notify('comunicacao.hoje', [$uid], $msg, ['publication_ids' => array_map(static fn($p) => (int) $p['id'], $list)], 'publicacoes:' . $uid . ':' . date('Y-m-d'));
        }
        return count($byUser);
    }

    // ---- Destinatários por papel -------------------------------------------

    public static function roleIds(array $roles): array
    {
        $in = "'" . implode("','", array_map(static fn($r) => preg_replace('/[^a-z_]/', '', $r), $roles)) . "'";
        return array_map('intval', array_column(Database::all(
            "SELECT u.id FROM users u JOIN user_roles r ON r.user_id = u.id AND r.app_code = :app WHERE u.status = 'ativo' AND r.role IN ({$in})",
            ['app' => APP_CODE]
        ), 'id'));
    }

    /** Coordenadores da função (is_coordinator) + admins; sem coordenador específico, todos os coordenadores. */
    public static function coordinatorsFor(int $functionId): array
    {
        $specific = array_map('intval', array_column(Database::all(
            "SELECT mf.user_id FROM member_functions mf JOIN users u ON u.id = mf.user_id WHERE mf.function_id = :f AND mf.is_coordinator = 1 AND u.status = 'ativo'",
            ['f' => $functionId]
        ), 'user_id'));
        return array_values(array_unique(array_merge($specific ?: self::roleIds(['coordenador']), self::roleIds(['admin']))));
    }
}
