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

$removedHits = Database::run('DELETE FROM rate_limit_hits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->rowCount();

// Cadastros pendentes sem aprovação há mais de 90 dias são anonimizados (minimização de dados)
$stale = Database::all("SELECT id FROM users WHERE status = 'pendente' AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
foreach ($stale as $row) {
    User::anonymize((int) $row['id']);
    Logger::audit('cadastro_pendente_expirado', 'users', $row['id']);
}

// Logs de aplicação com mais de 12 meses
$removedLogs = 0;
foreach (glob(LOG_PATH . '/app-*.log') ?: [] as $file) {
    if (filemtime($file) < strtotime('-12 months')) {
        @unlink($file);
        $removedLogs++;
    }
}

Logger::info('Limpeza executada', ['rate_limit' => $removedHits, 'pendentes_expirados' => count($stale), 'logs' => $removedLogs]);
echo date('Y-m-d H:i:s') . " limpeza ok: rate_limit={$removedHits} pendentes=" . count($stale) . " logs={$removedLogs}\n";
