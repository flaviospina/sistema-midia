<?php
// app/models/Tag.php — tags livres
declare(strict_types=1);

final class Tag
{
    public static function slugify(string $name): string
    {
        $slug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $slug), '-'));
        return substr($slug, 0, 60);
    }

    /** Converte "culto, jovens, 2026" em ids (criando as inexistentes). */
    public static function idsFromText(string $text): array
    {
        $ids = [];
        foreach (array_unique(array_filter(array_map('trim', explode(',', $text)))) as $name) {
            $name = mb_substr($name, 0, 60);
            $slug = self::slugify($name);
            if ($slug === '') {
                continue;
            }
            $id = Database::value('SELECT id FROM tags WHERE slug = :s', ['s' => $slug]);
            if ($id === null) {
                $id = Database::insert('INSERT INTO tags (name, slug) VALUES (:n, :s)', ['n' => $name, 's' => $slug]);
            }
            $ids[] = (int) $id;
        }
        return $ids;
    }

    public static function sync(int $fileId, string $text): void
    {
        Database::run('DELETE FROM file_tags WHERE file_id = :f', ['f' => $fileId]);
        foreach (self::idsFromText($text) as $tid) {
            Database::run('INSERT IGNORE INTO file_tags (file_id, tag_id) VALUES (:f, :t)', ['f' => $fileId, 't' => $tid]);
        }
    }

    public static function popular(int $limit = 30): array
    {
        return Database::all(
            'SELECT t.name, t.slug, COUNT(ft.file_id) AS qty FROM tags t JOIN file_tags ft ON ft.tag_id = t.id
              GROUP BY t.id, t.name, t.slug ORDER BY qty DESC, t.name LIMIT :l',
            ['l' => $limit]
        );
    }

    public static function textFor(array $tags): string
    {
        return implode(', ', array_column($tags, 'name'));
    }
}
