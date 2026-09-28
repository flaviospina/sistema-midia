<?php
// app/routes.php — mapa de rotas (permissões checadas no servidor em cada uma)
declare(strict_types=1);

$r = new Router();

// Acesso
$r->get('/login', [AuthController::class, 'loginForm'], ['guest' => true]);
$r->post('/login', [AuthController::class, 'login'], ['guest' => true]);
$r->post('/sair', [AuthController::class, 'logout'], ['gate' => false]);
$r->get('/trocar-senha', [AuthController::class, 'changePasswordForm'], ['gate' => false]);
$r->post('/trocar-senha', [AuthController::class, 'changePassword'], ['gate' => false]);
$r->get('/termo', [AuthController::class, 'termsForm'], ['gate' => false]);
$r->post('/termo', [AuthController::class, 'acceptTerms'], ['gate' => false]);
$r->get('/termo-de-uso', [AuthController::class, 'termsPublic'], ['auth' => false]);
$r->get('/esqueci-senha', [AuthController::class, 'forgot'], ['auth' => false]);
$r->get('/cadastro', [RegisterController::class, 'form'], ['guest' => true]);
$r->post('/cadastro', [RegisterController::class, 'store'], ['guest' => true]);

// Painel
$r->get('/', [DashboardController::class, 'index']);

// Pessoas (usuários e equipe de mídia)
$r->get('/usuarios', [UserController::class, 'index'], ['perm' => 'users.view']);
$r->get('/usuarios/pendentes', [UserController::class, 'pending'], ['perm' => 'users.approve']);
$r->post('/usuarios/{id}/aprovar', [UserController::class, 'approve'], ['perm' => 'users.approve']);
$r->post('/usuarios/{id}/recusar', [UserController::class, 'reject'], ['perm' => 'users.approve']);
$r->get('/usuarios/novo', [UserController::class, 'create'], ['perm' => 'users.manage']);
$r->post('/usuarios', [UserController::class, 'store'], ['perm' => 'users.manage']);
$r->get('/usuarios/{id}', [UserController::class, 'show'], ['perm' => 'users.view']);
$r->get('/usuarios/{id}/editar', [UserController::class, 'edit'], ['perm' => 'users.manage']);
$r->post('/usuarios/{id}', [UserController::class, 'update'], ['perm' => 'users.manage']);
$r->get('/usuarios/{id}/funcoes', [UserController::class, 'functionsForm'], ['perm' => 'users.functions']);
$r->post('/usuarios/{id}/funcoes', [UserController::class, 'saveFunctions'], ['perm' => 'users.functions']);
$r->post('/usuarios/{id}/redefinir-senha', [UserController::class, 'resetPassword'], ['perm' => 'users.manage']);
$r->post('/usuarios/{id}/status', [UserController::class, 'toggleStatus'], ['perm' => 'users.manage']);
$r->post('/usuarios/{id}/anonimizar', [UserController::class, 'anonymize'], ['perm' => 'privacy.manage']);
$r->get('/usuarios/{id}/foto', [UserController::class, 'photo']); // permissão checada no controller

// Ministérios
$r->get('/ministerios', [MinistryController::class, 'index'], ['perm' => 'ministries.view']);
$r->get('/ministerios/novo', [MinistryController::class, 'create'], ['perm' => 'ministries.manage']);
$r->post('/ministerios', [MinistryController::class, 'store'], ['perm' => 'ministries.manage']);
$r->get('/ministerios/{id}/editar', [MinistryController::class, 'edit'], ['perm' => 'ministries.manage']);
$r->post('/ministerios/{id}', [MinistryController::class, 'update'], ['perm' => 'ministries.manage']);

// Funções da mídia
$r->get('/funcoes', [FunctionController::class, 'index'], ['perm' => 'functions.manage']);
$r->get('/funcoes/novo', [FunctionController::class, 'create'], ['perm' => 'functions.manage']);
$r->post('/funcoes', [FunctionController::class, 'store'], ['perm' => 'functions.manage']);
$r->get('/funcoes/{id}/editar', [FunctionController::class, 'edit'], ['perm' => 'functions.manage']);
$r->post('/funcoes/{id}', [FunctionController::class, 'update'], ['perm' => 'functions.manage']);

// Auditoria
$r->get('/auditoria', [AuditController::class, 'index'], ['perm' => 'audit.view']);
$r->get('/auditoria/{id}', [AuditController::class, 'show'], ['perm' => 'audit.view']);

// LGPD — titular
$r->get('/meus-dados', [ProfileController::class, 'index']);
$r->post('/meus-dados', [ProfileController::class, 'update']);
$r->post('/meus-dados/senha', [ProfileController::class, 'changePassword']);
$r->get('/meus-dados/exportar', [ProfileController::class, 'export']);
$r->post('/meus-dados/solicitacao', [ProfileController::class, 'request']);
$r->post('/meus-dados/revogar', [ProfileController::class, 'revoke']);

// LGPD — administração
$r->get('/privacidade', [PrivacyController::class, 'index'], ['perm' => 'privacy.manage']);
$r->post('/privacidade/{id}/resolver', [PrivacyController::class, 'resolve'], ['perm' => 'privacy.manage']);

return $r;
