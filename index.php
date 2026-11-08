<?php
// index.php — front controller do Envio de Mídias ADMoema (toda URL amigável passa por aqui via .htaccess)
declare(strict_types=1);

define('APP_ROOT', __DIR__);
require APP_ROOT . '/app/core/bootstrap.php';

$router = require APP_ROOT . '/app/routes.php';
$router->dispatch();
