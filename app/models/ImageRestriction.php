<?php
// app/models/ImageRestriction.php — pessoas que NÃO autorizam uso de imagem
declare(strict_types=1);

final class ImageRestriction
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT r.*, (SELECT COUNT(*) FROM file_restrictions fr WHERE fr.restriction_id = r.id) AS files_count FROM image_restrictions r';
        if ($onlyActive) {
            $sql .= ' WHERE r.active = 1';
        }
        return Database::all($sql . ' ORDER BY r.active DESC, r.person_name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM image_restrictions WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO image_restrictions (person_name, is_minor, guardian_name, contact, notes, active, created_by)
             VALUES (:person_name, :is_minor, :guardian_name, :contact, :notes, :active, :by)',
            $d + ['by' => Auth::id()]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE image_restrictions SET person_name = :person_name, is_minor = :is_minor, guardian_name = :guardian_name,
                    contact = :contact, notes = :notes, active = :active WHERE id = :id',
            $d + ['id' => $id]
        );
    }

    public static function setPhoto(int $id, ?string $path): void
    {
        Database::run('UPDATE image_restrictions SET photo_path = :p WHERE id = :id', ['p' => $path, 'id' => $id]);
    }
}
