<?php
// app/models/ScheduleTemplate.php — modelos de escala reutilizáveis (vagas por função)
declare(strict_types=1);

final class ScheduleTemplate
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT t.*, (SELECT GROUP_CONCAT(CONCAT(f.name, " ×", s.quantity) ORDER BY f.sort_order SEPARATOR ", ")
                               FROM schedule_template_slots s JOIN media_functions f ON f.id = s.function_id WHERE s.template_id = t.id) AS summary
                  FROM schedule_templates t';
        if ($onlyActive) {
            $sql .= ' WHERE t.active = 1';
        }
        return Database::all($sql . ' ORDER BY t.name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM schedule_templates WHERE id = :id', ['id' => $id]);
    }

    public static function slots(int $id): array
    {
        return Database::all(
            'SELECT s.*, f.name AS function_name FROM schedule_template_slots s JOIN media_functions f ON f.id = s.function_id WHERE s.template_id = :t ORDER BY f.sort_order',
            ['t' => $id]
        );
    }

    /** [function_id => quantity] */
    public static function slotMap(int $id): array
    {
        $out = [];
        foreach (self::slots($id) as $s) {
            $out[(int) $s['function_id']] = (int) $s['quantity'];
        }
        return $out;
    }

    public static function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM schedule_templates WHERE name = :n';
        $params = ['n' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    public static function save(?int $id, array $d, array $slots): int
    {
        return Database::transaction(static function () use ($id, $d, $slots): int {
            if ($id === null) {
                $id = Database::insert('INSERT INTO schedule_templates (name, description, active) VALUES (:name, :description, :active)', $d);
            } else {
                Database::run('UPDATE schedule_templates SET name = :name, description = :description, active = :active WHERE id = :id', $d + ['id' => $id]);
            }
            Database::run('DELETE FROM schedule_template_slots WHERE template_id = :t', ['t' => $id]);
            foreach ($slots as $fid => $qty) {
                $qty = max(0, min(20, (int) $qty));
                if ($qty > 0) {
                    Database::run('INSERT INTO schedule_template_slots (template_id, function_id, quantity) VALUES (:t, :f, :q)', ['t' => $id, 'f' => (int) $fid, 'q' => $qty]);
                }
            }
            return $id;
        });
    }
}
