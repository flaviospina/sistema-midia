<?php
// public/paths.php
// Caminho da pasta que contém app/, storage/, sql/ e o arquivo .env.
// Opção A do LEIA-ME (app fora do public_html): ajuste para o caminho absoluto,
// por exemplo: define('APP_ROOT', '/home/usuario/midia_app');
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
