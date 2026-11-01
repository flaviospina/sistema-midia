<?php
// app/models/Album.php — álbuns: uma pasta física por culto/evento (AAAA-MM-DD Nome do evento)
declare(strict_types=1);

final class Album
{
    /** Nome sugerido pelo dia da semana (0 = domingo). Segunda e sexta não têm culto fixo. */
    public const WEEKDAY_NAMES = [
        0 => 'Culto com a Família',
        2 => 'Culto de Ensino',
        3 => 'Círculo de Oração',
        4 => 'Culto Público',
        6 => 'Culto dos Jovens e Adolescentes',
    ];

    public static function suggestName(string $date): ?string
    {
        $ts = strtotime($date);
        return $ts ? (self::WEEKDAY_NAMES[(int) date('w', $ts)] ?? null) : null;
    }

    /** Nome de pasta seguro: só letras sem acento, números, espaço, hífen e sublinhado. */
    public static function folderFor(string $date, string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $clean = trim(preg_replace('/[^A-Za-z0-9 _-]+/', '', $ascii) ?? '');
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        return $date . ' ' . ($clean !== '' ? mb_substr($clean, 0, 100) : 'Evento');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT a.*, u.name AS creator_name FROM photo_albums a LEFT JOIN users u ON u.id = a.created_by WHERE a.id = :id', ['id' => $id]);
    }

    public static function findByFolder(string $folder): ?array
    {
        return Database::one('SELECT * FROM photo_albums WHERE folder = :f', ['f' => $folder]);
    }

    /** Álbuns visíveis para o usuário atual, agrupados por mês. */
    public static function listVisible(?int $year = null, string $q = ''): array
    {
        $conds = Auth::can('albums.hidden') ? ['1=1'] : ['a.hidden = 0'];
        $params = [];
        if ($year) {
            $conds[] = 'YEAR(a.event_date) = :y';
            $params['y'] = $year;
        }
        if ($q !== '') {
            $conds[] = '(a.name LIKE :q1 OR a.description LIKE :q2)';
            $params['q1'] = $params['q2'] = '%' . $q . '%';
        }
        return Database::all(
            'SELECT a.*, c.file_name AS cover_file, c.id AS cover_id FROM photo_albums a LEFT JOIN photos c ON c.id = a.cover_photo_id
              WHERE ' . implode(' AND ', $conds) . ' ORDER BY a.event_date DESC, a.id DESC',
            $params
        );
    }

    public static function years(): array
    {
        $hidden = Auth::can('albums.hidden') ? '' : ' WHERE hidden = 0';
        return array_map('intval', array_column(Database::all("SELECT DISTINCT YEAR(event_date) y FROM photo_albums{$hidden} ORDER BY y DESC"), 'y'));
    }

    public static function create(string $date, string $name, ?string $description, bool $hidden): int
    {
        $folder = self::folderFor($date, $name);
        $dir = ALBUMS_PATH . '/' . $folder;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new RuntimeException('Não foi possível criar a pasta no servidor. Verifique as permissões de storage/albums.');
        }
        @file_put_contents(ALBUMS_PATH . '/.htaccess', "Require all denied\n");
        return Database::insert(
            'INSERT INTO photo_albums (event_date, name, folder, description, hidden, created_by) VALUES (:d, :n, :f, :desc, :h, :by)',
            ['d' => $date, 'n' => $name, 'f' => $folder, 'desc' => $description, 'h' => $hidden ? 1 : 0, 'by' => Auth::id()]
        );
    }

    public static function update(int $id, string $name, ?string $description, bool $hidden): void
    {
        Database::run('UPDATE photo_albums SET name = :n, description = :d, hidden = :h WHERE id = :id', ['n' => $name, 'd' => $description, 'h' => $hidden ? 1 : 0, 'id' => $id]);
    }

    public static function setCover(int $id, ?int $photoId): void
    {
        Database::run('UPDATE photo_albums SET cover_photo_id = :p WHERE id = :id', ['p' => $photoId, 'id' => $id]);
    }

    /** Recalcula contadores e capa (chamado após envio/exclusão). */
    public static function refreshStats(int $id): void
    {
        Database::run(
            'UPDATE photo_albums a SET photos_count = (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id),
                    size_bytes = (SELECT COALESCE(SUM(size_bytes),0) FROM photos p WHERE p.album_id = a.id),
                    cover_photo_id = COALESCE((SELECT id FROM photos p WHERE p.id = a.cover_photo_id), (SELECT MIN(id) FROM photos p WHERE p.album_id = a.id AND p.has_thumb = 1))
              WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function delete(array $album): void
    {
        Database::run('DELETE FROM photo_albums WHERE id = :id', ['id' => $album['id']]);
        $dir = ALBUMS_PATH . '/' . $album['folder'];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    public static function canView(array $album): bool
    {
        return (int) $album['hidden'] === 0 || Auth::can('albums.hidden');
    }

    public static function totals(): array
    {
        return Database::one('SELECT COUNT(*) AS albums, COALESCE(SUM(photos_count),0) AS photos, COALESCE(SUM(size_bytes),0) AS bytes FROM photo_albums') ?? ['albums' => 0, 'photos' => 0, 'bytes' => 0];
    }
}
