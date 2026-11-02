<?php
// public/paths.php — onde fica a pasta do aplicativo (app/, storage/, sql/ e o arquivo .env).
//
// Detecta automaticamente as duas estruturas comuns:
//   1) tudo em public_html/midia: index.php ao lado de app/           → APP_ROOT = esta pasta
//   2) repositório como veio: public/index.php e app/ um nível acima   → APP_ROOT = pasta acima
// Opção recomendada (app fora da pasta pública): descomente e ajuste a linha abaixo.
declare(strict_types=1);

// define('APP_ROOT', '/home/USUARIO/midia_app');

if (!defined('APP_ROOT')) {
    foreach ([__DIR__, dirname(__DIR__)] as $candidate) {
        if (is_file($candidate . '/app/bootstrap.php')) {
            define('APP_ROOT', $candidate);
            break;
        }
    }
}
if (!defined('APP_ROOT')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Pasta do aplicativo não encontrada.\n\nEdite public_html/midia/paths.php e informe o caminho completo da pasta que contém app/, storage/ e .env, por exemplo:\n\n    define('APP_ROOT', '/home/USUARIO/midia_app');\n");
}
