<?php
// app/services/Scheduler.php — sugestão automática com rodízio justo e alertas de conflito/sobrecarga
declare(strict_types=1);

final class Scheduler
{
    /**
     * Candidatos para uma função num evento, ordenados do mais "descansado" ao mais escalado.
     * Cada item: user + level + stats + flags (unavailable, in_event, conflict, overload).
     */
    public static function candidates(array $event, int $functionId, bool $onlyEligible = true): array
    {
        $rows = Database::all(
            "SELECT u.id, u.name, u.photo_path, mf.level, m.member_status
               FROM member_functions mf
               JOIN users u ON u.id = mf.user_id
               JOIN media_members m ON m.user_id = u.id
              WHERE mf.function_id = :f AND u.status = 'ativo'
              ORDER BY u.name",
            ['f' => $functionId]
        );
        $stats = Assignment::rotationStats(SCHEDULE_ROTATION_DAYS);
        $unavailable = Unavailability::unavailableUserIds($event['starts_at'], $event['ends_at']);
        $inEvent = Assignment::userIdsInEvent((int) $event['id']);

        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r['id'];
            $st = $stats[$uid] ?? ['recent' => 0, 'month' => 0, 'last' => null];
            $c = $r + [
                'recent'      => $st['recent'],
                'month'       => $st['month'],
                'last'        => $st['last'],
                'unavailable' => in_array($uid, $unavailable, true),
                'in_event'    => in_array($uid, $inEvent, true),
                'conflicts'   => Assignment::conflictsForUser($uid, $event),
                'overload'    => $st['month'] >= SCHEDULE_OVERLOAD_PER_MONTH,
                'eligible'    => in_array($r['level'], ['apto', 'referencia'], true) && $r['member_status'] === 'ativo',
            ];
            $c['blocked'] = $c['unavailable'] || $c['in_event'] || $c['conflicts'] !== [] || !$c['eligible'];
            if ($onlyEligible && !$c['eligible']) {
                continue;
            }
            $out[] = $c;
        }
        usort($out, static function (array $a, array $b): int {
            // Bloqueados por último; depois menos escalas recentes; depois última escala mais antiga; depois referência primeiro
            return [$a['blocked'], $a['recent'], $a['last'] ?? '0000', $a['level'] === 'referencia' ? 0 : 1, $a['name']]
                <=> [$b['blocked'], $b['recent'], $b['last'] ?? '0000', $b['level'] === 'referencia' ? 0 : 1, $b['name']];
        });
        return $out;
    }

    /**
     * Sugestão para todas as vagas abertas do evento.
     * @return array function_id => [candidatos escolhidos]
     */
    public static function suggest(array $event): array
    {
        $current = Assignment::forEvent((int) $event['id']);
        $chosenIds = Assignment::userIdsInEvent((int) $event['id']);
        $out = [];
        foreach (Event::slots((int) $event['id']) as $slot) {
            $fid = (int) $slot['function_id'];
            $filled = count(array_filter($current[$fid] ?? [], static fn($a) => $a['status'] !== 'recusado'));
            $need = (int) $slot['quantity'] - $filled;
            if ($need <= 0) {
                continue;
            }
            $picked = [];
            $hasReference = false;
            foreach (self::candidates($event, $fid) as $c) {
                if ($c['blocked'] || in_array((int) $c['id'], $chosenIds, true)) {
                    continue;
                }
                $picked[] = $c;
                $chosenIds[] = (int) $c['id'];
                $hasReference = $hasReference || $c['level'] === 'referencia';
                if (count($picked) >= $need) {
                    break;
                }
            }
            // Garante ao menos uma pessoa "referência" na função quando houver mais de uma vaga
            if ($need >= 2 && $picked && !$hasReference) {
                foreach (self::candidates($event, $fid) as $c) {
                    if ($c['level'] === 'referencia' && !$c['blocked'] && !in_array((int) $c['id'], $chosenIds, true)) {
                        $removed = array_pop($picked);
                        $chosenIds = array_diff($chosenIds, [(int) $removed['id']]);
                        $picked[] = $c;
                        $chosenIds[] = (int) $c['id'];
                        break;
                    }
                }
            }
            $out[$fid] = $picked;
        }
        return $out;
    }

    /** Alertas de uma escala já montada (sobrecarga, conflito, indisponibilidade). */
    public static function warnings(array $event, array $assignments): array
    {
        $warnings = [];
        $stats = Assignment::rotationStats(SCHEDULE_ROTATION_DAYS);
        $unavailable = Unavailability::unavailableUserIds($event['starts_at'], $event['ends_at']);
        $seen = [];
        foreach ($assignments as $list) {
            foreach ($list as $a) {
                if ($a['status'] === 'recusado') {
                    continue;
                }
                $uid = (int) $a['user_id'];
                if (isset($seen[$uid])) {
                    $warnings[] = ['type' => 'danger', 'text' => $a['user_name'] . ' está em mais de uma função neste evento.'];
                }
                $seen[$uid] = true;
                if (in_array($uid, $unavailable, true)) {
                    $warnings[] = ['type' => 'danger', 'text' => $a['user_name'] . ' informou indisponibilidade neste horário.'];
                }
                foreach (Assignment::conflictsForUser($uid, $event) as $c) {
                    $warnings[] = ['type' => 'danger', 'text' => $a['user_name'] . ' já está escalado em "' . $c['title'] . '" (' . format_datetime($c['starts_at']) . ').'];
                }
                if (($stats[$uid]['month'] ?? 0) >= SCHEDULE_OVERLOAD_PER_MONTH) {
                    $warnings[] = ['type' => 'warning', 'text' => $a['user_name'] . ' tem ' . $stats[$uid]['month'] . ' escalas em 30 dias (sobrecarga).'];
                }
                if (!in_array($a['level'], ['apto', 'referencia'], true)) {
                    $warnings[] = ['type' => 'warning', 'text' => $a['user_name'] . ' ainda é aprendiz em ' . $a['function_name'] . '.'];
                }
            }
        }
        return $warnings;
    }
}
