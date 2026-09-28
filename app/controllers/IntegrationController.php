<?php
// app/controllers/IntegrationController.php — painel de integrações (n8n/WhatsApp): status, teste, fila, avisos ligados
declare(strict_types=1);

final class IntegrationController
{
    public function index(): never
    {
        view('integrations/index', [
            'title'      => 'Integrações',
            'configured' => N8N_WEBHOOK_URL !== '' && N8N_WEBHOOK_SECRET !== '',
            'inbound'    => N8N_INBOUND_SECRET !== '',
            'enabled'    => NOTIFY_ENABLED,
            'url'        => N8N_WEBHOOK_URL,
            'stats'      => Notification::stats(),
            'items'      => Notification::recent(60),
            'events'     => Setting::EVENTS,
            'inboundLog' => Database::all('SELECT i.*, u.name AS user_name FROM inbound_messages i LEFT JOIN users u ON u.id = i.user_id ORDER BY i.id DESC LIMIT 20'),
            'lastDaily'  => Setting::get('notify.last_daily_run'),
            'curl'       => function_exists('curl_init'),
        ]);
    }

    /** Dispara um aviso de teste para o próprio admin (passa pelo n8n de verdade). */
    public function test(): never
    {
        $me = Auth::user();
        if (!$me['whatsapp']) {
            flash('warning', 'Cadastre seu WhatsApp em "Meus dados" para receber o teste.');
            redirect('/integracoes');
        }
        $id = Notification::enqueue('teste', Notifier::recipients([(int) $me['id']]), '✅ Teste da Central de Mídia ADMoema: a integração com o n8n está funcionando. ' . date('d/m/Y H:i'), ['por' => $me['name']]);
        $ok = Notifier::dispatch(Notification::find($id));
        $n = Notification::find($id);
        Logger::audit('integracao_teste', 'notifications', $id, null, ['ok' => $ok, 'http' => $n['response_code']]);
        flash($ok ? 'success' : 'danger', $ok ? 'Aviso de teste aceito pelo n8n (HTTP ' . (int) $n['response_code'] . '). Confira seu WhatsApp.' : 'O n8n não aceitou o aviso: ' . ($n['last_error'] ?? 'erro desconhecido'));
        redirect('/integracoes');
    }

    public function flush(): never
    {
        [$ok, $fail] = Notifier::flush(50);
        flash($fail ? 'warning' : 'success', "Fila processada: {$ok} enviado(s), {$fail} falha(s).");
        redirect('/integracoes');
    }

    public function retry(int $id): never
    {
        Notification::find($id) ?? abort(404);
        Notification::retry($id);
        Notifier::dispatch(Notification::find($id));
        redirect('/integracoes');
    }

    public function cancel(int $id): never
    {
        Notification::find($id) ?? abort(404);
        Notification::cancel($id);
        redirect('/integracoes');
    }

    public function saveEvents(): never
    {
        $on = (array) ($_POST['events'] ?? []);
        foreach (array_keys(Setting::EVENTS) as $ev) {
            Setting::set('notify.' . $ev, in_array($ev, $on, true) ? '1' : '0');
        }
        Logger::audit('integracao_eventos', 'settings', null, null, ['ligados' => array_values($on)]);
        flash('success', 'Avisos atualizados.');
        redirect('/integracoes');
    }
}
