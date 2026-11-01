<?php
// public/index.php — front controller
declare(strict_types=1);

require __DIR__ . '/paths.php';
require APP_ROOT . '/app/bootstrap.php';

$router = require APP_ROOT . '/app/routes.php';
$router->dispatch();
