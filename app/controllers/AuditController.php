<?php
// app/controllers/AuditController.php
declare(strict_types=1);

final class AuditController
{
    public function index(): never
    {
        $filters = [
            'user_id' => preg_match('/^\d+$/', query('usuario')) ? query('usuario') : '',
            'entity'  => query('entidade'),
            'action'  => query('acao'),
            'from'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', query('de')) ? query('de') : '',
            'to'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', query('ate')) ? query('ate') : '',
        ];
        view('audit/index', [
            'title'    => 'Auditoria',
            'filters'  => $filters,
            'result'   => AuditLog::search($filters, current_page()),
            'entities' => AuditLog::entities(),
        ]);
    }

    public function show(int $id): never
    {
        $entry = AuditLog::find($id) ?? abort(404);
        view('audit/show', ['title' => 'Registro #' . $id, 'entry' => $entry]);
    }
}
