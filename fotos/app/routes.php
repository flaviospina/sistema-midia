<?php
// app/routes.php — rotas do sistema de fotos (permissões checadas no servidor em toda rota)
declare(strict_types=1);

$r = new Router();

// Acesso
$r->get('/login', [AuthController::class, 'loginForm'], ['guest' => true]);
$r->post('/login', [AuthController::class, 'login'], ['guest' => true]);
$r->get('/login/codigo', [AuthController::class, 'codeForm'], ['guest' => true]);
$r->post('/login/codigo', [AuthController::class, 'code'], ['guest' => true]);
$r->post('/login/codigo/reenviar', [AuthController::class, 'resendCode'], ['guest' => true]);
$r->post('/sair', [AuthController::class, 'logout']);
$r->get('/trocar-senha', [AuthController::class, 'changePasswordForm'], ['gate' => false]);
$r->post('/trocar-senha', [AuthController::class, 'changePassword'], ['gate' => false]);
$r->get('/esqueci-senha', [PasswordResetController::class, 'form'], ['guest' => true]);
$r->post('/esqueci-senha', [PasswordResetController::class, 'request'], ['guest' => true]);
$r->get('/redefinir-senha/{token}', [PasswordResetController::class, 'resetForm'], ['guest' => true]);
$r->post('/redefinir-senha/{token}', [PasswordResetController::class, 'reset'], ['guest' => true]);

// Álbuns (pastas por culto/evento)
$r->get('/', [AlbumController::class, 'home'], ['perm' => 'albums.view']);
$r->get('/albuns/novo', [AlbumController::class, 'create'], ['perm' => 'albums.manage']);
$r->get('/albuns/sugestao', [AlbumController::class, 'suggest'], ['perm' => 'albums.manage']);
$r->post('/albuns', [AlbumController::class, 'store'], ['perm' => 'albums.manage']);
$r->get('/albuns/{id}', [AlbumController::class, 'show'], ['perm' => 'albums.view']);
$r->get('/albuns/{id}/editar', [AlbumController::class, 'edit'], ['perm' => 'albums.manage']);
$r->post('/albuns/{id}', [AlbumController::class, 'update'], ['perm' => 'albums.manage']);
$r->post('/albuns/{id}/excluir', [AlbumController::class, 'delete'], ['perm' => 'albums.manage']);
$r->post('/albuns/{id}/zip', [AlbumController::class, 'zip'], ['perm' => 'photos.download']);

// Envio em pedaços
$r->get('/albuns/{id}/enviar', [UploadController::class, 'page'], ['perm' => 'photos.upload']);
$r->post('/api/upload/iniciar', [UploadController::class, 'start'], ['perm' => 'photos.upload']);
$r->post('/api/upload/pedaco', [UploadController::class, 'chunk'], ['perm' => 'photos.upload']);
$r->post('/api/upload/concluir', [UploadController::class, 'finish'], ['perm' => 'photos.upload']);
$r->post('/api/upload/cancelar', [UploadController::class, 'cancel'], ['perm' => 'photos.upload']);

// Fotos
$r->get('/fotos/{id}/miniatura', [PhotoController::class, 'thumb'], ['perm' => 'albums.view']);
$r->get('/fotos/{id}/imagem', [PhotoController::class, 'display'], ['perm' => 'albums.view']);
$r->get('/fotos/{id}/original', [PhotoController::class, 'download'], ['perm' => 'photos.download']);
$r->post('/fotos/{id}/legenda', [PhotoController::class, 'caption'], ['perm' => 'photos.upload']);
$r->post('/fotos/{id}/excluir', [PhotoController::class, 'delete'], ['perm' => 'photos.upload']);
$r->post('/fotos/{id}/capa', [PhotoController::class, 'setCover'], ['perm' => 'albums.manage']);
$r->get('/fotos/{id}/melhorar', [PhotoController::class, 'editor'], ['perm' => 'photos.edit']);
$r->post('/fotos/{id}/versao', [PhotoController::class, 'saveVersion'], ['perm' => 'photos.save_version']);
$r->get('/fotos/{id}/compartilhar', [SocialController::class, 'form'], ['perm' => 'social.share']);
$r->post('/fotos/{id}/compartilhar', [SocialController::class, 'publish'], ['perm' => 'social.share']);
// Link público temporário (a Meta baixa a imagem por aqui)
$r->get('/p/{token}.jpg', [PhotoController::class, 'publicImage'], ['auth' => false]);

// Conta
$r->get('/meus-dados', [AccountController::class, 'index']);
$r->post('/meus-dados', [AccountController::class, 'update']);
$r->post('/meus-dados/aparelhos/esquecer', [AccountController::class, 'forgetDevices']);
$r->post('/meus-dados/redes/descobrir', [AccountController::class, 'socialDiscover'], ['perm' => 'social.share']);
$r->post('/meus-dados/redes/salvar', [AccountController::class, 'socialSave'], ['perm' => 'social.share']);
$r->post('/meus-dados/redes/remover', [AccountController::class, 'socialRemove'], ['perm' => 'social.share']);

// Administração
$r->get('/admin/usuarios', [AdminController::class, 'users'], ['perm' => 'users.manage']);
$r->post('/admin/usuarios', [AdminController::class, 'store'], ['perm' => 'users.manage']);
$r->post('/admin/usuarios/conceder', [AdminController::class, 'grant'], ['perm' => 'users.manage']);
$r->post('/admin/usuarios/{id}/perfil', [AdminController::class, 'role'], ['perm' => 'users.manage']);
$r->post('/admin/usuarios/{id}/ativar', [AdminController::class, 'toggle'], ['perm' => 'users.manage']);
$r->post('/admin/usuarios/{id}/remover', [AdminController::class, 'revoke'], ['perm' => 'users.manage']);
$r->post('/admin/usuarios/{id}/senha', [AdminController::class, 'resetPassword'], ['perm' => 'users.manage']);
$r->get('/admin/auditoria', [AdminController::class, 'audit'], ['perm' => 'audit.view']);

return $r;
