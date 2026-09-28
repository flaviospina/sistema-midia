<?php
// app/controllers/ChecklistController.php — checklist pré-culto (preenchimento por evento e configuração por função)
declare(strict_types=1);

final class ChecklistController
{
    public function fill(int $eventId): never
    {
        $e = Event::find($eventId) ?? abort(404);
        $assignments = Assignment::forEvent($eventId);
        $myFunctions = [];
        foreach ($assignments as $fid => $list) {
            foreach ($list as $a) {
                if ((int) $a['user_id'] === Auth::id() && $a['status'] !== 'recusado') {
                    $myFunctions[] = $fid;
                }
            }
        }
        view('checklist/fill', [
            'title'       => 'Checklist: ' . $e['title'],
            'e'           => $e,
            'byFunction'  => Checklist::itemsByFunction(),
            'checks'      => Checklist::checks($eventId),
            'myFunctions' => $myFunctions,
            'canAll'      => Auth::can('checklist.manage'),
            'assignments' => $assignments,
        ]);
    }

    public function save(int $eventId, int $functionId): never
    {
        $e = Event::find($eventId) ?? abort(404);
        $mine = (bool) Database::value("SELECT COUNT(*) FROM assignments WHERE event_id = :e AND function_id = :f AND user_id = :u AND status <> 'recusado'", ['e' => $eventId, 'f' => $functionId, 'u' => Auth::id()]);
        if (!$mine && !Auth::can('checklist.manage')) {
            abort(403, 'Você não está escalado(a) nesta função neste evento.');
        }
        Checklist::saveFunction($eventId, $functionId, (array) ($_POST['items'] ?? []));
        $p = Checklist::progress($eventId)[$functionId] ?? ['done' => 0, 'total' => 0];
        Logger::audit('checklist_preenchido', 'events', $eventId, null, ['function_id' => $functionId, 'done' => $p['done'], 'total' => $p['total']]);
        flash('success', 'Checklist salvo (' . $p['done'] . '/' . $p['total'] . ').');
        redirect('/eventos/' . $eventId . '/checklist');
    }

    public function config(): never
    {
        view('checklist/config', ['title' => 'Checklist pré-culto', 'byFunction' => Checklist::itemsByFunction(false), 'functions' => MediaFunction::all(true)]);
    }

    public function saveConfig(int $functionId): never
    {
        MediaFunction::find($functionId) ?? abort(404);
        Checklist::saveItems($functionId, (array) ($_POST['items'] ?? []));
        Logger::audit('checklist_config', 'checklist_items', $functionId);
        flash('success', 'Itens atualizados.');
        redirect('/checklist');
    }
}
