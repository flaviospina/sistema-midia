<?php
// cron/limpeza.php — remove envios abandonados, códigos/links expirados e limites antigos.
// cPanel → Cron Jobs: a cada hora →  php /home/USUARIO/fotos_app/cron/limpeza.php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

$uploads = Upload::purgeExpired();
LoginCode::purge();
PublicLink::purge();
Database::run('DELETE FROM rate_limit_hits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
Database::run('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
// Arquivos temporários órfãos (ZIPs não apagados, pastas de envio sem registro)
foreach (glob(TMP_PATH . '/*') ?: [] as $f) {
    if (filemtime($f) < time() - UPLOAD_SESSION_HOURS * 3600) {
        if (is_dir($f)) {
            foreach (glob($f . '/*') ?: [] as $g) {
                @unlink($g);
            }
            @rmdir($f);
        } else {
            @unlink($f);
        }
    }
}
echo date('Y-m-d H:i:s') . " limpeza: {$uploads} envio(s) expirado(s) removido(s)\n";
