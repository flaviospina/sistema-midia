<?php
// cron/limpeza.php — manutenção diária
// cPanel → Cron Jobs: 0 3 * * *  /usr/local/bin/php /home/USUARIO/CAMINHO/cron/limpeza.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente via linha de comando.');
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

$report = [];

// Controle de tentativas (login, cadastro, /enviar)
$report['rate_limit'] = Database::run('DELETE FROM rate_limit_hits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->rowCount();

// Cadastros pendentes sem aprovação há mais de 90 dias são anonimizados (minimização de dados)
$stale = Database::all("SELECT id FROM users WHERE status = 'pendente' AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
foreach ($stale as $row) {
    User::anonymize((int) $row['id']);
    Logger::audit('cadastro_pendente_expirado', 'users', $row['id']);
}
$report['pendentes_expirados'] = count($stale);

// Sessões de upload expiradas (pedaços em storage/tmp_chunks)
$report['uploads_expirados'] = UploadService::cleanupExpired();

// Arquivos rejeitados na quarentena: apagados após RETENTION_REJECTED_DAYS
$rejected = Database::all("SELECT * FROM files WHERE status = 'rejeitado' AND moderated_at < DATE_SUB(NOW(), INTERVAL :d DAY)", ['d' => RETENTION_REJECTED_DAYS]);
foreach ($rejected as $f) {
    MediaFile::purge($f);
    Logger::audit('arquivo_rejeitado_apagado', 'files', $f['id'], null, ['nome' => $f['original_name']]);
}
$report['rejeitados_apagados'] = count($rejected);

// Lixeira: apagados após RETENTION_TRASH_DAYS
$trashed = Database::all("SELECT * FROM files WHERE status = 'lixeira' AND trashed_at < DATE_SUB(NOW(), INTERVAL :d DAY)", ['d' => RETENTION_TRASH_DAYS]);
foreach ($trashed as $f) {
    MediaFile::purge($f);
    Logger::audit('arquivo_lixeira_apagado', 'files', $f['id'], null, ['nome' => $f['original_name']]);
}
$report['lixeira_apagados'] = count($trashed);

// Links de compartilhamento vencidos há mais de 90 dias
$report['links_removidos'] = Database::run('DELETE FROM share_links WHERE expires_at IS NOT NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 90 DAY)')->rowCount();

// Registros de convidado sem nenhum arquivo, com mais de 2 dias (formulário preenchido sem envio)
$report['convidados_vazios'] = Database::run(
    'DELETE g FROM guest_uploads g
      WHERE g.created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)
        AND NOT EXISTS (SELECT 1 FROM files f WHERE f.guest_upload_id = g.id)
        AND NOT EXISTS (SELECT 1 FROM upload_sessions s WHERE s.guest_upload_id = g.id)'
)->rowCount();

// Arquivos temporários órfãos (miniaturas/zip abandonados há mais de 1 dia)
$report['temporarios'] = 0;
foreach (glob(Storage::tmpDir() . '/{thumb,zip}_*', GLOB_BRACE) ?: [] as $tmp) {
    if (is_file($tmp) && filemtime($tmp) < time() - 86400) {
        @unlink($tmp);
        $report['temporarios']++;
    }
}

// Alerta de espaço
$usage = array_sum(Storage::driver()->usage());
if (STORAGE_ALERT_GB > 0 && $usage > STORAGE_ALERT_GB * 1024 ** 3) {
    Logger::error('Armazenamento acima do limite de alerta', ['usado' => format_bytes($usage), 'limite_gb' => STORAGE_ALERT_GB]);
    $report['alerta_espaco'] = format_bytes($usage);
}

// Logs de aplicação com mais de 12 meses
$report['logs'] = 0;
foreach (glob(LOG_PATH . '/app-*.log') ?: [] as $file) {
    if (filemtime($file) < strtotime('-12 months')) {
        @unlink($file);
        $report['logs']++;
    }
}

Logger::info('Limpeza executada', $report);
echo date('Y-m-d H:i:s') . ' limpeza ok: ' . json_encode($report, JSON_UNESCAPED_UNICODE) . "\n";
