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

// ---------------------------------------------------------------------
// Repositório de arquivos (Fase 2)
// ---------------------------------------------------------------------
$r->get('/arquivos', [FileController::class, 'index'], ['perm' => 'files.browse']);
$r->get('/arquivos/buscar', [FileController::class, 'search'], ['perm' => 'files.browse']);
$r->get('/arquivos/enviar', [FileController::class, 'uploadForm'], ['perm' => 'files.upload']);
$r->get('/arquivos/lixeira', [FileController::class, 'trash'], ['perm' => 'files.moderate']);
$r->post('/arquivos/lixeira/esvaziar', [FileController::class, 'emptyTrash'], ['perm' => 'files.moderate']);
$r->post('/arquivos/zip', [FileController::class, 'zipSelection'], ['perm' => 'files.browse']);
$r->get('/arquivos/{id}', [FileController::class, 'show'], ['perm' => 'files.browse']);
$r->get('/arquivos/{id}/editar', [FileController::class, 'edit'], ['perm' => 'files.browse']);
$r->post('/arquivos/{id}', [FileController::class, 'update'], ['perm' => 'files.browse']);
$r->post('/arquivos/{id}/lixeira', [FileController::class, 'trashFile'], ['perm' => 'files.browse']);
$r->post('/arquivos/{id}/restaurar', [FileController::class, 'restore'], ['perm' => 'files.moderate']);
$r->get('/arquivos/{id}/download', [DownloadController::class, 'download'], ['perm' => 'files.browse']);
$r->get('/arquivos/{id}/original', [DownloadController::class, 'original'], ['perm' => 'files.original']);
$r->get('/arquivos/{id}/ver', [DownloadController::class, 'view'], ['perm' => 'files.browse']);
$r->get('/arquivos/{id}/miniatura', [DownloadController::class, 'thumb'], ['perm' => 'files.browse']);

$r->get('/pastas/nova', [FolderController::class, 'create'], ['perm' => 'folders.manage']);
$r->post('/pastas', [FolderController::class, 'store'], ['perm' => 'folders.manage']);
$r->get('/pastas/{id}', [FileController::class, 'folder'], ['perm' => 'files.browse']);
$r->get('/pastas/{id}/editar', [FolderController::class, 'edit'], ['perm' => 'folders.manage']);
$r->post('/pastas/{id}', [FolderController::class, 'update'], ['perm' => 'folders.manage']);
$r->post('/pastas/{id}/excluir', [FolderController::class, 'delete'], ['perm' => 'folders.manage']);
$r->get('/pastas/{id}/zip', [DownloadController::class, 'zipFolder'], ['perm' => 'files.browse']);

// Upload em pedaços (usuário logado OU convidado com sessão de /enviar — verificado no controller)
$r->post('/api/upload/iniciar', [UploadController::class, 'start'], ['auth' => false]);
$r->post('/api/upload/pedaco', [UploadController::class, 'chunk'], ['auth' => false]);
$r->get('/api/upload/{token}/status', [UploadController::class, 'status'], ['auth' => false]);
$r->post('/api/upload/concluir', [UploadController::class, 'finish'], ['auth' => false]);
$r->post('/api/upload/cancelar', [UploadController::class, 'cancel'], ['auth' => false]);

// Moderação
$r->get('/moderacao', [ModerationController::class, 'index'], ['perm' => 'files.moderate']);
$r->post('/moderacao/{id}/aprovar', [ModerationController::class, 'approve'], ['perm' => 'files.moderate']);
$r->post('/moderacao/{id}/rejeitar', [ModerationController::class, 'reject'], ['perm' => 'files.moderate']);

// Compartilhamento
$r->get('/compartilhamentos', [ShareController::class, 'index'], ['perm' => 'files.share']);
$r->post('/compartilhamentos', [ShareController::class, 'create'], ['perm' => 'files.share']);
$r->post('/compartilhamentos/{id}/desativar', [ShareController::class, 'deactivate'], ['perm' => 'files.share']);
$r->get('/compartilhar/{token}', [ShareController::class, 'publicPage'], ['auth' => false]);
$r->get('/compartilhar/{token}/zip', [ShareController::class, 'publicZip'], ['auth' => false]);
$r->get('/compartilhar/{token}/arquivo/{id}', [ShareController::class, 'publicFile'], ['auth' => false]);
$r->get('/compartilhar/{token}/miniatura/{id}', [ShareController::class, 'publicThumb'], ['auth' => false]);

// Direito de imagem
$r->get('/restricoes', [RestrictionController::class, 'index'], ['perm' => 'restrictions.view']);
$r->get('/restricoes/nova', [RestrictionController::class, 'create'], ['perm' => 'restrictions.manage']);
$r->post('/restricoes', [RestrictionController::class, 'store'], ['perm' => 'restrictions.manage']);
$r->get('/restricoes/{id}/editar', [RestrictionController::class, 'edit'], ['perm' => 'restrictions.manage']);
$r->post('/restricoes/{id}', [RestrictionController::class, 'update'], ['perm' => 'restrictions.manage']);
$r->get('/restricoes/{id}/foto', [RestrictionController::class, 'photo'], ['perm' => 'restrictions.view']);

// Armazenamento
$r->get('/armazenamento', [StorageController::class, 'index'], ['perm' => 'storage.view']);

// Página pública de envio (convidados)
$r->get('/enviar', [GuestController::class, 'form'], ['auth' => false]);
$r->post('/enviar', [GuestController::class, 'start'], ['auth' => false]);
$r->get('/enviar/arquivos', [GuestController::class, 'files'], ['auth' => false]);
$r->get('/enviar/concluido', [GuestController::class, 'done'], ['auth' => false]);

return $r;
