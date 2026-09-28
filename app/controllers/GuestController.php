<?php
// app/controllers/GuestController.php — página pública /enviar (convidados, sem login)
declare(strict_types=1);

final class GuestController
{
    public function form(): never
    {
        view('guest/form', [
            'title'      => 'Enviar fotos e vídeos',
            'ministries' => Ministry::all(true),
            'events'     => $this->recentEvents(),
            'captcha'    => Captcha::enabled(),
        ], 'layout_auth');
    }

    public function start(): never
    {
        if (input('website') !== '') { // honeypot
            Logger::info('Honeypot acionado em /enviar');
            redirect('/enviar/concluido');
        }
        if (RateLimit::count('guest_form', client_ip(), 60) >= 20) {
            flash('danger', 'Muitas tentativas a partir desta conexão. Tente mais tarde.');
            redirect('/enviar');
        }
        $v = (new Validator($_POST))
            ->required('guest_name', 'seu nome')->max('guest_name', 150, 'Nome')
            ->required('whatsapp', 'seu WhatsApp')->whatsapp('whatsapp')
            ->max('event_other', 150, 'Evento')->max('description', 2000, 'Descrição');
        if (input('accept') !== '1') {
            $v->add('accept', 'É necessário aceitar a declaração de uso de imagem.');
        }
        if (!Captcha::verify((string) ($_POST['h-captcha-response'] ?? ''))) {
            $v->add('captcha', 'Confirme que você não é um robô.');
        }
        $ministryId = ctype_digit(input('ministry_id')) ? (int) input('ministry_id') : null;
        $ministry = $ministryId ? Ministry::find($ministryId) : null;
        $event = input('event') === '__outro' ? input('event_other') : input('event');
        if ($v->fails()) {
            with_errors($v->errors(), $_POST);
            redirect('/enviar');
        }
        RateLimit::hit('guest_form', client_ip());
        $g = GuestUpload::create([
            'guest_name'    => input('guest_name'),
            'whatsapp'      => Validator::normalizePhone(input('whatsapp')),
            'ministry_id'   => $ministry ? (int) $ministry['id'] : null,
            'ministry_name' => $ministry ? $ministry['name'] : (mb_substr(input('ministry_other'), 0, 120) ?: null),
            'event_name'    => mb_substr($event, 0, 150) ?: null,
            'description'   => input('description') ?: null,
        ]);
        $_SESSION['guest_upload_id'] = $g['id'];
        Logger::audit('convidado_iniciou_envio', 'guest_uploads', $g['id'], null, ['nome' => input('guest_name'), 'evento' => $event]);
        redirect('/enviar/arquivos');
    }

    public function files(): never
    {
        $gid = (int) ($_SESSION['guest_upload_id'] ?? 0);
        $guest = $gid ? GuestUpload::find($gid) : null;
        if (!$guest) {
            flash('info', 'Preencha seus dados para começar o envio.');
            redirect('/enviar');
        }
        view('guest/upload', [
            'title'   => 'Enviar arquivos',
            'guest'   => $guest,
            'sent'    => Database::all('SELECT id, original_name, size_bytes, status, created_at FROM files WHERE guest_upload_id = :g ORDER BY id DESC', ['g' => $gid]),
            'maxBytes' => min(GUEST_MAX_FILE_BYTES, UPLOAD_MAX_BYTES),
        ], 'layout_auth');
    }

    public function done(): never
    {
        unset($_SESSION['guest_upload_id']);
        view('guest/done', ['title' => 'Envio concluído'], 'layout_auth');
    }

    /** Eventos recentes informados em envios (a tabela de eventos chega na Fase 3). */
    private function recentEvents(): array
    {
        return array_column(Database::all(
            'SELECT event_name, MAX(created_at) AS last FROM files WHERE event_name IS NOT NULL AND event_name <> "" AND created_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
              GROUP BY event_name ORDER BY last DESC LIMIT 12'
        ), 'event_name');
    }
}
