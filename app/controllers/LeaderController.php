<?php
// app/controllers/LeaderController.php — painel do líder (indicadores consolidados + gráficos)
declare(strict_types=1);

final class LeaderController
{
    public function index(): never
    {
        $days = in_array(query('periodo'), ['30', '90', '180', '365'], true) ? (int) query('periodo') : 90;
        $from = date('Y-m-d', strtotime("-{$days} days"));
        $to = date('Y-m-d', strtotime('+30 days'));

        // Frequência: confirmados/pendentes/recusados por pessoa
        $byUser = Assignment::statsByUser($from, $to . ' 23:59:59');
        $stats = Assignment::rotationStats(SCHEDULE_ROTATION_DAYS);
        $overloaded = [];
        foreach ($stats as $uid => $st) {
            if ($st['month'] >= SCHEDULE_OVERLOAD_PER_MONTH && ($u = User::find($uid))) {
                $overloaded[] = ['name' => $u['name'], 'month' => $st['month']];
            }
        }
        // Repositório: uploads por mês (últimos 6)
        $uploads = Database::all("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) qty, COALESCE(SUM(size_bytes),0) bytes FROM files WHERE status <> 'lixeira' AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym");
        $art = ArtRequest::countsForDashboard();

        view('leader/index', [
            'title'       => 'Painel do líder',
            'days'        => $days,
            'byUser'      => $byUser,
            'overloaded'  => $overloaded,
            'declines'    => array_values(array_filter($byUser, static fn($u) => (int) $u['recusados'] >= 2)),
            'openSlots'   => Assignment::openSlotsSummary(21),
            'art'         => $art,
            'artDelays'   => ArtRequest::delays(),
            'ministries'  => ArtRequest::topMinistries($days),
            'uploads'     => $uploads,
            'storage'     => MediaFile::totals(),
            'byCategory'  => MediaFile::usageByCategory(),
            'quarantine'  => MediaFile::countQuarantine(),
            'incidents'   => Incident::statsByKind($days),
            'openIncidents' => Incident::countOpen(),
            'audience'    => Incident::audienceSeries(10),
            'equipment'   => Equipment::summary(),
            'overdue'     => Equipment::overdueLoans(),
            'ready'       => Training::readyToPromote(),
            'pendingReports' => Incident::eventsWithoutReport(30),
        ]);
    }
}
