<?php
// app/models/Training.php — trilha de capacitação por função e progresso por pessoa
declare(strict_types=1);

final class Training
{
    public static function byFunction(bool $onlyActive = true): array
    {
        $rows = Database::all(
            'SELECT t.*, f.name AS function_name, fi.original_name AS file_name FROM trainings t JOIN media_functions f ON f.id = t.function_id LEFT JOIN files fi ON fi.id = t.file_id'
            . ($onlyActive ? ' WHERE t.active = 1' : '') . ' ORDER BY f.sort_order, t.sort_order, t.id'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['function_id']]['name'] = $r['function_name'];
            $out[(int) $r['function_id']]['items'][] = $r;
        }
        return $out;
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT t.*, f.name AS function_name FROM trainings t JOIN media_functions f ON f.id = t.function_id WHERE t.id = :id', ['id' => $id]);
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO trainings (function_id, title, description, resource_url, file_id, sort_order, required, active) VALUES (:function_id, :title, :description, :resource_url, :file_id, :sort_order, :required, :active)',
            $d
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run('UPDATE trainings SET function_id = :function_id, title = :title, description = :description, resource_url = :resource_url, file_id = :file_id, sort_order = :sort_order, required = :required, active = :active WHERE id = :id', $d + ['id' => $id]);
    }

    /** Progresso de uma pessoa: training_id => linha (completed_at, validated_at, ...) */
    public static function progressOf(int $userId): array
    {
        $out = [];
        foreach (Database::all('SELECT p.*, v.name AS validator_name FROM training_progress p LEFT JOIN users v ON v.id = p.validated_by WHERE p.user_id = :u', ['u' => $userId]) as $r) {
            $out[(int) $r['training_id']] = $r;
        }
        return $out;
    }

    public static function complete(int $userId, int $trainingId, ?string $notes): void
    {
        Database::run(
            'INSERT INTO training_progress (user_id, training_id, notes) VALUES (:u, :t, :n) ON DUPLICATE KEY UPDATE notes = COALESCE(VALUES(notes), notes)',
            ['u' => $userId, 't' => $trainingId, 'n' => $notes]
        );
    }

    public static function validate(int $userId, int $trainingId, bool $validated): void
    {
        if ($validated) {
            Database::run(
                'INSERT INTO training_progress (user_id, training_id, validated_by, validated_at) VALUES (:u, :t, :v, NOW()) ON DUPLICATE KEY UPDATE validated_by = :v2, validated_at = NOW()',
                ['u' => $userId, 't' => $trainingId, 'v' => Auth::id(), 'v2' => Auth::id()]
            );
        } else {
            Database::run('UPDATE training_progress SET validated_by = NULL, validated_at = NULL WHERE user_id = :u AND training_id = :t', ['u' => $userId, 't' => $trainingId]);
        }
    }

    public static function uncomplete(int $userId, int $trainingId): void
    {
        Database::run('DELETE FROM training_progress WHERE user_id = :u AND training_id = :t', ['u' => $userId, 't' => $trainingId]);
    }

    /**
     * Situação da trilha de uma pessoa numa função: [required, done, validated, complete(bool)].
     * "complete" = todos os obrigatórios validados pelo coordenador.
     */
    public static function status(int $userId, int $functionId): array
    {
        $r = Database::one(
            'SELECT COUNT(*) AS required, SUM(p.training_id IS NOT NULL) AS done, SUM(p.validated_at IS NOT NULL) AS validated
               FROM trainings t LEFT JOIN training_progress p ON p.training_id = t.id AND p.user_id = :u
              WHERE t.function_id = :f AND t.active = 1 AND t.required = 1',
            ['u' => $userId, 'f' => $functionId]
        ) ?: ['required' => 0, 'done' => 0, 'validated' => 0];
        $required = (int) $r['required'];
        return ['required' => $required, 'done' => (int) $r['done'], 'validated' => (int) $r['validated'], 'complete' => $required > 0 && (int) $r['validated'] >= $required];
    }

    /** Aprendizes com trilha completa e ainda não "apto" (para o coordenador promover). */
    public static function readyToPromote(): array
    {
        $out = [];
        $members = Database::all(
            "SELECT mf.user_id, mf.function_id, u.name, f.name AS function_name FROM member_functions mf JOIN users u ON u.id = mf.user_id JOIN media_functions f ON f.id = mf.function_id
              WHERE mf.level = 'aprendiz' AND u.status = 'ativo' ORDER BY u.name"
        );
        foreach ($members as $m) {
            $st = self::status((int) $m['user_id'], (int) $m['function_id']);
            if ($st['complete']) {
                $out[] = $m + $st;
            }
        }
        return $out;
    }

    /** Visão da equipe: por pessoa × função, progresso. */
    public static function teamOverview(): array
    {
        $rows = Database::all(
            "SELECT mf.user_id, u.name, mf.function_id, f.name AS function_name, mf.level FROM member_functions mf JOIN users u ON u.id = mf.user_id JOIN media_functions f ON f.id = mf.function_id
              WHERE u.status = 'ativo' ORDER BY u.name, f.sort_order"
        );
        foreach ($rows as &$r) {
            $r += self::status((int) $r['user_id'], (int) $r['function_id']);
        }
        return $rows;
    }
}
