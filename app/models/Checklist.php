<?php
// app/models/Checklist.php — checklist pré-culto por função (itens e marcações por evento)
declare(strict_types=1);

final class Checklist
{
    /** Itens ativos agrupados por função: [function_id => ['name' => ..., 'items' => [...]]] */
    public static function itemsByFunction(bool $onlyActive = true): array
    {
        $rows = Database::all(
            'SELECT c.*, f.name AS function_name, f.sort_order AS fsort FROM checklist_items c JOIN media_functions f ON f.id = c.function_id'
            . ($onlyActive ? ' WHERE c.active = 1 AND f.active = 1' : '') . ' ORDER BY f.sort_order, c.sort_order, c.id'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['function_id']]['name'] = $r['function_name'];
            $out[(int) $r['function_id']]['items'][] = $r;
        }
        return $out;
    }

    /** Marcações de um evento: item_id => [checked_by, checked_at, user_name] */
    public static function checks(int $eventId): array
    {
        $out = [];
        foreach (Database::all('SELECT c.*, u.name AS user_name FROM event_checklist_checks c LEFT JOIN users u ON u.id = c.checked_by WHERE c.event_id = :e', ['e' => $eventId]) as $r) {
            $out[(int) $r['item_id']] = $r;
        }
        return $out;
    }

    public static function toggle(int $eventId, int $itemId, bool $checked): void
    {
        if ($checked) {
            Database::run('INSERT IGNORE INTO event_checklist_checks (event_id, item_id, checked_by) VALUES (:e, :i, :u)', ['e' => $eventId, 'i' => $itemId, 'u' => Auth::id()]);
        } else {
            Database::run('DELETE FROM event_checklist_checks WHERE event_id = :e AND item_id = :i', ['e' => $eventId, 'i' => $itemId]);
        }
    }

    /** Salva o conjunto marcado de uma função (só os itens daquela função). */
    public static function saveFunction(int $eventId, int $functionId, array $checkedIds): void
    {
        $items = array_map('intval', array_column(Database::all('SELECT id FROM checklist_items WHERE function_id = :f AND active = 1', ['f' => $functionId]), 'id'));
        foreach ($items as $iid) {
            self::toggle($eventId, $iid, in_array($iid, array_map('intval', $checkedIds), true));
        }
    }

    /** Progresso por função num evento: [function_id => [done, total]] */
    public static function progress(int $eventId): array
    {
        $out = [];
        foreach (Database::all(
            'SELECT c.function_id, COUNT(*) total, SUM(x.item_id IS NOT NULL) done FROM checklist_items c
          LEFT JOIN event_checklist_checks x ON x.item_id = c.id AND x.event_id = :e WHERE c.active = 1 GROUP BY c.function_id',
            ['e' => $eventId]
        ) as $r) {
            $out[(int) $r['function_id']] = ['done' => (int) $r['done'], 'total' => (int) $r['total']];
        }
        return $out;
    }

    public static function saveItems(int $functionId, array $rows): void
    {
        Database::transaction(static function () use ($functionId, $rows): void {
            $keep = [];
            foreach ($rows as $row) {
                $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 150);
                if ($label === '') {
                    continue;
                }
                $order = (int) ($row['sort_order'] ?? 0);
                if (!empty($row['id']) && ctype_digit((string) $row['id'])) {
                    Database::run('UPDATE checklist_items SET label = :l, sort_order = :o, active = 1 WHERE id = :id AND function_id = :f', ['l' => $label, 'o' => $order, 'id' => (int) $row['id'], 'f' => $functionId]);
                    $keep[] = (int) $row['id'];
                } else {
                    $keep[] = Database::insert('INSERT INTO checklist_items (function_id, label, sort_order) VALUES (:f, :l, :o)', ['f' => $functionId, 'l' => $label, 'o' => $order]);
                }
            }
            $in = $keep ? implode(',', $keep) : '0';
            Database::run("UPDATE checklist_items SET active = 0 WHERE function_id = :f AND id NOT IN ({$in})", ['f' => $functionId]);
        });
    }
}
