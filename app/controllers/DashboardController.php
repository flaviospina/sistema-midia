<?php
// app/controllers/DashboardController.php — painel inicial
declare(strict_types=1);

final class DashboardController
{
    public function index(): never
    {
        $user = Auth::user();
        $data = ['title' => 'Início', 'user' => $user];

        if (Auth::can('users.view')) {
            $data['stats'] = [
                'equipe'    => (int) Database::value("SELECT COUNT(*) FROM media_members m JOIN users u ON u.id = m.user_id WHERE u.status = 'ativo' AND m.member_status = 'ativo'"),
                'treino'    => (int) Database::value("SELECT COUNT(*) FROM media_members m JOIN users u ON u.id = m.user_id WHERE u.status = 'ativo' AND m.member_status = 'em_treinamento'"),
                'usuarios'  => (int) Database::value("SELECT COUNT(*) FROM users WHERE status = 'ativo'"),
                'pendentes' => Auth::can('users.approve') ? User::countPending() : 0,
                'lgpd'      => Auth::can('privacy.manage') ? DataRequest::countOpen() : 0,
            ];
            $data['byFunction'] = Database::all(
                "SELECT f.name,
                        SUM(mf.level = 'aprendiz') AS aprendiz,
                        SUM(mf.level = 'apto') AS apto,
                        SUM(mf.level = 'referencia') AS referencia
                   FROM media_functions f
              LEFT JOIN member_functions mf ON mf.function_id = f.id
              LEFT JOIN users u ON u.id = mf.user_id AND u.status = 'ativo'
                  WHERE f.active = 1
               GROUP BY f.id, f.name, f.sort_order
               ORDER BY f.sort_order"
            );
        }
        if (Auth::can('schedule.self')) {
            $data['myAssignments'] = Assignment::forUser((int) $user['id'], 0, 30);
            $data['mySwaps'] = array_filter(SwapRequest::forUser((int) $user['id']), static fn($s) => (int) $s['to_user_id'] === (int) $user['id'] && $s['status'] === 'aguardando_membro');
        }
        if (Auth::can('schedule.manage')) {
            $data['openSlots'] = array_filter(Assignment::openSlotsSummary(14), static fn($o) => (int) $o['slots_total'] > (int) $o['assigned_total'] || (int) $o['declined_total'] > 0);
            $data['pendingSwaps'] = count(SwapRequest::pendingForCoordinator());
        }
        if (Auth::can('events.view')) {
            $data['upcoming'] = Event::upcoming(7, 6);
        }
        if (Auth::can('art.request')) {
            $data['art'] = ArtRequest::countsForDashboard();
            $data['artMine'] = ArtRequest::search(['mine' => true], 1, 6)['itens'];
        }
        if (Auth::can('publications.manage')) {
            $data['pubs'] = Publication::upcoming(7);
        }
        if (Auth::can('files.moderate')) {
            $data['quarantine'] = MediaFile::countQuarantine();
        }
        if (Auth::can('files.browse')) {
            $data['recentFiles'] = MediaFile::search(['order' => 'recentes'], 1, 8)['itens'];
        }
        if (Auth::can('audit.view')) {
            $data['recentAudit'] = AuditLog::recent(8);
        }
        if (Auth::isMedia()) {
            $data['myFunctions'] = MediaFunction::forMember((int) $user['id']);
        }
        $data['myMinistries'] = User::ministries((int) $user['id']);

        view('dashboard', $data);
    }
}
