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
$r->get('/esqueci-senha', [PasswordResetController::class, 'form'], ['guest' => true]);
$r->post('/esqueci-senha', [PasswordResetController::class, 'request'], ['guest' => true]);
$r->get('/redefinir-senha/{token}', [PasswordResetController::class, 'resetForm'], ['guest' => true]);
$r->post('/redefinir-senha/{token}', [PasswordResetController::class, 'reset'], ['guest' => true]);
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

// ---------------------------------------------------------------------
// Eventos e escala (Fase 3)
// ---------------------------------------------------------------------
$r->get('/eventos', [EventController::class, 'index'], ['perm' => 'events.view']);
$r->get('/eventos/novo', [EventController::class, 'create'], ['perm' => 'events.manage']);
$r->post('/eventos', [EventController::class, 'store'], ['perm' => 'events.manage']);
$r->get('/eventos/{id}', [EventController::class, 'show'], ['perm' => 'events.view']);
$r->get('/eventos/{id}/editar', [EventController::class, 'edit'], ['perm' => 'events.manage']);
$r->post('/eventos/{id}', [EventController::class, 'update'], ['perm' => 'events.manage']);
$r->post('/eventos/{id}/cancelar', [EventController::class, 'cancel'], ['perm' => 'events.manage']);
$r->post('/eventos/{id}/reativar', [EventController::class, 'reactivate'], ['perm' => 'events.manage']);
$r->post('/eventos/{id}/excluir', [EventController::class, 'delete'], ['perm' => 'events.manage']);

$r->get('/eventos/{id}/escala', [ScheduleController::class, 'edit'], ['perm' => 'schedule.manage']);
$r->post('/eventos/{id}/escala/vagas', [ScheduleController::class, 'slots'], ['perm' => 'events.manage']);
$r->post('/eventos/{id}/escala/modelo', [ScheduleController::class, 'template'], ['perm' => 'events.manage']);
$r->post('/eventos/{id}/escala/escalar', [ScheduleController::class, 'assign'], ['perm' => 'schedule.manage']);
$r->post('/eventos/{id}/escala/sugerir', [ScheduleController::class, 'applySuggestion'], ['perm' => 'schedule.manage']);
$r->post('/escalas/{id}/remover', [ScheduleController::class, 'remove'], ['perm' => 'schedule.manage']);
$r->get('/escala/painel', [ScheduleController::class, 'dashboard'], ['perm' => 'schedule.manage']);
$r->get('/trocas', [ScheduleController::class, 'swaps'], ['perm' => 'schedule.manage']);
$r->post('/trocas/{id}/aprovar', [ScheduleController::class, 'approveSwap'], ['perm' => 'schedule.manage']);
$r->post('/trocas/{id}/rejeitar', [ScheduleController::class, 'rejectSwap'], ['perm' => 'schedule.manage']);

$r->get('/minha-escala', [MyScheduleController::class, 'index'], ['perm' => 'schedule.self']);
$r->post('/escalas/{id}/responder', [MyScheduleController::class, 'respond'], ['perm' => 'schedule.self']);
$r->post('/escalas/{id}/troca', [MyScheduleController::class, 'requestSwap'], ['perm' => 'schedule.self']);
$r->post('/trocas/{id}/aceitar', [MyScheduleController::class, 'acceptSwap'], ['perm' => 'schedule.self']);
$r->post('/trocas/{id}/recusar', [MyScheduleController::class, 'declineSwap'], ['perm' => 'schedule.self']);
$r->post('/trocas/{id}/cancelar', [MyScheduleController::class, 'cancelSwap'], ['perm' => 'schedule.self']);
$r->post('/minha-escala/ics/renovar', [MyScheduleController::class, 'regenerateIcs'], ['perm' => 'schedule.self']);
$r->get('/calendario/{token}.ics', [MyScheduleController::class, 'ics'], ['auth' => false]);

$r->get('/indisponibilidades', [UnavailabilityController::class, 'index'], ['perm' => 'schedule.self']);
$r->post('/indisponibilidades', [UnavailabilityController::class, 'store'], ['perm' => 'schedule.self']);
$r->post('/indisponibilidades/{id}/excluir', [UnavailabilityController::class, 'delete'], ['perm' => 'schedule.self']);
$r->get('/indisponibilidades/equipe', [UnavailabilityController::class, 'team'], ['perm' => 'unavailability.view']);

$r->get('/recorrencias', [RecurrenceController::class, 'index'], ['perm' => 'events.manage']);
$r->get('/recorrencias/nova', [RecurrenceController::class, 'create'], ['perm' => 'events.manage']);
$r->post('/recorrencias', [RecurrenceController::class, 'store'], ['perm' => 'events.manage']);
$r->post('/recorrencias/gerar', [RecurrenceController::class, 'generate'], ['perm' => 'events.manage']);
$r->get('/recorrencias/{id}/editar', [RecurrenceController::class, 'edit'], ['perm' => 'events.manage']);
$r->post('/recorrencias/{id}', [RecurrenceController::class, 'update'], ['perm' => 'events.manage']);
$r->get('/modelos', [RecurrenceController::class, 'templates'], ['perm' => 'events.manage']);
$r->get('/modelos/novo', [RecurrenceController::class, 'templateCreate'], ['perm' => 'events.manage']);
$r->post('/modelos', [RecurrenceController::class, 'templateStore'], ['perm' => 'events.manage']);
$r->get('/modelos/{id}/editar', [RecurrenceController::class, 'templateEdit'], ['perm' => 'events.manage']);
$r->post('/modelos/{id}', [RecurrenceController::class, 'templateUpdate'], ['perm' => 'events.manage']);

// ---------------------------------------------------------------------
// Pedidos de arte e calendário de comunicação (Fase 4)
// ---------------------------------------------------------------------
$r->get('/artes', [ArtController::class, 'index'], ['perm' => 'art.request']);
$r->get('/artes/kanban', [ArtController::class, 'kanban'], ['perm' => 'art.produce']);
$r->get('/artes/atrasos', [ArtController::class, 'delays'], ['perm' => 'art.manage']);
$r->get('/artes/checklist', [ArtController::class, 'checklistConfig'], ['perm' => 'art.manage']);
$r->post('/artes/checklist', [ArtController::class, 'checklistSave'], ['perm' => 'art.manage']);
$r->get('/artes/novo', [ArtController::class, 'create'], ['perm' => 'art.request']);
$r->post('/artes', [ArtController::class, 'store'], ['perm' => 'art.request']);
$r->get('/artes/{id}', [ArtController::class, 'show'], ['perm' => 'art.request']);
$r->get('/artes/{id}/editar', [ArtController::class, 'edit'], ['perm' => 'art.request']);
$r->post('/artes/{id}', [ArtController::class, 'update'], ['perm' => 'art.request']);
$r->post('/artes/{id}/acao', [ArtController::class, 'action'], ['perm' => 'art.request']);
$r->post('/artes/{id}/designer', [ArtController::class, 'assign'], ['perm' => 'art.manage']);
$r->post('/artes/{id}/comentar', [ArtController::class, 'comment'], ['perm' => 'art.request']);
$r->post('/artes/{id}/checklist', [ArtController::class, 'checklist'], ['perm' => 'art.produce']);

$r->get('/comunicacao', [PublicationController::class, 'index'], ['perm' => 'publications.view']);
$r->get('/comunicacao/nova', [PublicationController::class, 'create'], ['perm' => 'publications.manage']);
$r->post('/comunicacao', [PublicationController::class, 'store'], ['perm' => 'publications.manage']);
$r->get('/comunicacao/{id}/editar', [PublicationController::class, 'edit'], ['perm' => 'publications.manage']);
$r->post('/comunicacao/{id}', [PublicationController::class, 'update'], ['perm' => 'publications.manage']);
$r->post('/comunicacao/{id}/publicar', [PublicationController::class, 'publish'], ['perm' => 'publications.manage']);
$r->post('/comunicacao/{id}/cancelar', [PublicationController::class, 'cancel'], ['perm' => 'publications.manage']);

// ---------------------------------------------------------------------
// Integrações n8n / WhatsApp (Fase 5)
// ---------------------------------------------------------------------
$r->get('/integracoes', [IntegrationController::class, 'index'], ['perm' => 'integrations.manage']);
$r->post('/integracoes/teste', [IntegrationController::class, 'test'], ['perm' => 'integrations.manage']);
$r->post('/integracoes/processar', [IntegrationController::class, 'flush'], ['perm' => 'integrations.manage']);
$r->post('/integracoes/eventos', [IntegrationController::class, 'saveEvents'], ['perm' => 'integrations.manage']);
$r->post('/integracoes/{id}/reenviar', [IntegrationController::class, 'retry'], ['perm' => 'integrations.manage']);
$r->post('/integracoes/{id}/cancelar', [IntegrationController::class, 'cancel'], ['perm' => 'integrations.manage']);
// Chamados pelo n8n (autenticação por segredo no header, sem sessão nem CSRF)
$r->get('/api/n8n/ping', [N8nController::class, 'ping'], ['auth' => false]);
$r->post('/api/n8n/entrada', [N8nController::class, 'inbound'], ['auth' => false, 'csrf' => false]);

// ---------------------------------------------------------------------
// Patrimônio, checklist, ocorrências, capacitação e painel do líder (Fase 6)
// ---------------------------------------------------------------------
$r->get('/patrimonio', [EquipmentController::class, 'index'], ['perm' => 'equipment.view']);
$r->get('/patrimonio/novo', [EquipmentController::class, 'create'], ['perm' => 'equipment.manage']);
$r->post('/patrimonio', [EquipmentController::class, 'store'], ['perm' => 'equipment.manage']);
$r->post('/patrimonio/etiquetas', [EquipmentController::class, 'labels'], ['perm' => 'equipment.manage']);
$r->get('/patrimonio/q/{token}', [EquipmentController::class, 'byToken'], ['perm' => 'equipment.view']);
$r->get('/patrimonio/{id}', [EquipmentController::class, 'show'], ['perm' => 'equipment.view']);
$r->get('/patrimonio/{id}/etiqueta', [EquipmentController::class, 'label'], ['perm' => 'equipment.view']);
$r->get('/patrimonio/{id}/foto', [EquipmentController::class, 'photo'], ['perm' => 'equipment.view']);
$r->get('/patrimonio/{id}/editar', [EquipmentController::class, 'edit'], ['perm' => 'equipment.manage']);
$r->post('/patrimonio/{id}', [EquipmentController::class, 'update'], ['perm' => 'equipment.manage']);
$r->post('/patrimonio/{id}/emprestar', [EquipmentController::class, 'loan'], ['perm' => 'equipment.view']);
$r->post('/patrimonio/{id}/devolver', [EquipmentController::class, 'returnLoan'], ['perm' => 'equipment.view']);
$r->post('/patrimonio/{id}/manutencao', [EquipmentController::class, 'openMaintenance'], ['perm' => 'equipment.manage']);
$r->post('/patrimonio/{id}/manutencao/{mid}/encerrar', [EquipmentController::class, 'closeMaintenance'], ['perm' => 'equipment.manage']);

$r->get('/eventos/{id}/checklist', [ChecklistController::class, 'fill'], ['perm' => 'checklist.fill']);
$r->post('/eventos/{id}/checklist/{fid}', [ChecklistController::class, 'save'], ['perm' => 'checklist.fill']);
$r->get('/checklist', [ChecklistController::class, 'config'], ['perm' => 'checklist.manage']);
$r->post('/checklist/{id}', [ChecklistController::class, 'saveConfig'], ['perm' => 'checklist.manage']);

$r->get('/ocorrencias', [IncidentController::class, 'index'], ['perm' => 'incidents.report']);
$r->get('/ocorrencias/nova', [IncidentController::class, 'create'], ['perm' => 'incidents.report']);
$r->post('/ocorrencias', [IncidentController::class, 'store'], ['perm' => 'incidents.report']);
$r->get('/ocorrencias/{id}', [IncidentController::class, 'show'], ['perm' => 'incidents.report']);
$r->get('/ocorrencias/{id}/editar', [IncidentController::class, 'edit'], ['perm' => 'incidents.report']);
$r->post('/ocorrencias/{id}', [IncidentController::class, 'update'], ['perm' => 'incidents.report']);
$r->post('/ocorrencias/{id}/status', [IncidentController::class, 'status'], ['perm' => 'incidents.manage']);
$r->get('/eventos/{id}/relatorio', [IncidentController::class, 'reportForm'], ['perm' => 'reports.fill']);
$r->post('/eventos/{id}/relatorio', [IncidentController::class, 'reportSave'], ['perm' => 'reports.fill']);

$r->get('/capacitacao', [TrainingController::class, 'index'], ['perm' => 'training.view']);
$r->post('/capacitacao/{id}/concluir', [TrainingController::class, 'complete'], ['perm' => 'training.view']);
$r->get('/capacitacao/equipe', [TrainingController::class, 'team'], ['perm' => 'training.manage']);
$r->get('/capacitacao/pessoa/{id}', [TrainingController::class, 'person'], ['perm' => 'training.manage']);
$r->post('/capacitacao/pessoa/{id}/validar/{tid}', [TrainingController::class, 'validateProgress'], ['perm' => 'training.manage']);
$r->post('/capacitacao/pessoa/{id}/promover/{fid}', [TrainingController::class, 'promote'], ['perm' => 'training.manage']);
$r->get('/capacitacao/trilhas', [TrainingController::class, 'config'], ['perm' => 'training.manage']);
$r->get('/capacitacao/trilhas/novo', [TrainingController::class, 'create'], ['perm' => 'training.manage']);
$r->post('/capacitacao/trilhas', [TrainingController::class, 'store'], ['perm' => 'training.manage']);
$r->get('/capacitacao/trilhas/{id}/editar', [TrainingController::class, 'edit'], ['perm' => 'training.manage']);
$r->post('/capacitacao/trilhas/{id}', [TrainingController::class, 'update'], ['perm' => 'training.manage']);

$r->get('/painel-lider', [LeaderController::class, 'index'], ['perm' => 'leader.dashboard']);

return $r;
