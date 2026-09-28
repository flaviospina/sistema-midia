<?php
// cron/notificacoes.php — envia a fila de avisos ao n8n e dispara as rotinas diárias
// cPanel → Cron Jobs: */5 * * * *  /usr/local/bin/php /home/USUARIO/CAMINHO/cron/notificacoes.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente via linha de comando.');
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

$report = [];

// Rotinas diárias: uma vez por dia, a partir de NOTIFY_DAILY_HOUR
$today = date('Y-m-d');
if ((int) date('G') >= NOTIFY_DAILY_HOUR && Setting::get('notify.last_daily_run') !== $today) {
    Setting::set('notify.last_daily_run', $today);
    $report['lembretes'] = Notifier::dailyReminders();
    $report['publicacoes_hoje'] = Notifier::dailyPublications();
}

// Fila (inclui os agrupados com atraso e as retentativas)
[$ok, $fail] = Notifier::flush(50);
$report['enviados'] = $ok;
$report['falhas'] = $fail;

if ($ok || $fail || isset($report['lembretes'])) {
    Logger::info('Notificações processadas', $report);
}
echo date('Y-m-d H:i:s') . ' notificacoes: ' . json_encode($report, JSON_UNESCAPED_UNICODE) . "\n";
