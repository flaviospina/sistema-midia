<?php
// app/services/Access.php — regras de visibilidade e permissão sobre pastas e arquivos
declare(strict_types=1);

final class Access
{
    /** Visibilidade efetiva de um arquivo (restrição de imagem sempre vence). */
    public static function fileVisibility(array $file): string
    {
        if ((int) ($file['has_restriction'] ?? 0) === 1) {
            return 'restrito';
        }
        return $file['visibility'] ?: Folder::effective($file['folder_id'] ? (int) $file['folder_id'] : null)['visibility'];
    }

    /** O usuário logado pode ver conteúdo com esta visibilidade nesta pasta? */
    public static function allows(string $visibility, ?int $folderId): bool
    {
        if (!Auth::check()) {
            return false;
        }
        if (Auth::is('admin')) {
            return true;
        }
        return match ($visibility) {
            'restrito'   => false,
            'midia'      => Auth::isMedia(),
            'ministerio' => Auth::isMedia() || self::inFolderMinistry($folderId),
            'membros'    => true,
            default      => false,
        };
    }

    private static function inFolderMinistry(?int $folderId): bool
    {
        $ministry = Folder::effective($folderId)['ministry_id'];
        return $ministry !== null && in_array($ministry, Auth::ministryIds(), true);
    }

    public static function canViewFolder(?int $folderId): bool
    {
        if ($folderId === null) {
            return Auth::can('files.browse');
        }
        $eff = Folder::effective($folderId);
        return Auth::can('files.browse') && self::allows($eff['visibility'], $folderId);
    }

    public static function canViewFile(array $file): bool
    {
        if (!Auth::can('files.browse')) {
            return false;
        }
        $mine = $file['uploaded_by'] !== null && (int) $file['uploaded_by'] === Auth::id();
        if (!empty($file['art_request_id']) && $file['status'] === 'aprovado') {
            $req = ArtRequest::find((int) $file['art_request_id']);
            if ($req && ArtRequest::canView($req)) {
                return true;
            }
        }
        if ($file['status'] === 'lixeira') {
            return Auth::can('files.moderate') || $mine;
        }
        if ($file['status'] !== 'aprovado') {
            return Auth::can('files.moderate') || $mine;
        }
        if ($mine && (int) $file['has_restriction'] === 0) {
            return true;
        }
        return self::allows(self::fileVisibility($file), $file['folder_id'] ? (int) $file['folder_id'] : null);
    }

    /** Envio direto (sem quarentena) para a pasta. */
    public static function canUploadTo(?int $folderId): bool
    {
        if ($folderId === null || !Auth::can('files.upload')) {
            return false;
        }
        if (Auth::can('folders.manage')) {
            return true;
        }
        $eff = Folder::effective($folderId);
        if (Auth::is('membro_midia')) {
            return $eff['visibility'] !== 'restrito';
        }
        if (Auth::is('lider_ministerio')) {
            return self::inFolderMinistry($folderId);
        }
        return false;
    }

    public static function canManageFile(array $file): bool
    {
        if (Auth::can('files.moderate')) {
            return true;
        }
        return $file['uploaded_by'] !== null && (int) $file['uploaded_by'] === Auth::id() && $file['status'] !== 'lixeira';
    }

    /** Pastas nas quais o usuário pode enviar diretamente. */
    public static function uploadableFolders(): array
    {
        return Folder::options(static fn(array $f): bool => self::canUploadTo((int) $f['id']));
    }

    /** IDs de pastas cujo conteúdo (herdado) o usuário pode ver. */
    public static function visibleFolderIds(): array
    {
        static $ids = null;
        if ($ids === null) {
            $ids = [];
            foreach (Folder::all() as $id => $f) {
                if (self::canViewFolder($id)) {
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }

    /**
     * Condição SQL (alias f) de arquivos visíveis para o usuário logado.
     * @return array{0:string,1:array}
     */
    public static function fileWhere(string $alias = 'f'): array
    {
        $uid = Auth::id() ?? 0;
        if (Auth::is('admin')) {
            return ["({$alias}.status = 'aprovado' OR {$alias}.uploaded_by = :acc_uid)", ['acc_uid' => $uid]];
        }
        $visibleFolders = self::visibleFolderIds();
        $folderIn = $visibleFolders ? implode(',', array_map('intval', $visibleFolders)) : '0';

        // Visibilidades que o perfil pode ver quando definidas no próprio arquivo
        $allowed = Auth::isMedia() ? ['midia', 'ministerio', 'membros'] : ['membros'];
        $allowedIn = "'" . implode("','", $allowed) . "'";

        // Para não-mídia, 'ministerio' definido no arquivo vale só em pastas do seu ministério
        $ministryFolders = [];
        if (!Auth::isMedia()) {
            foreach (Folder::all() as $id => $f) {
                $eff = Folder::effective($id);
                if ($eff['ministry_id'] !== null && in_array($eff['ministry_id'], Auth::ministryIds(), true)) {
                    $ministryFolders[] = $id;
                }
            }
        }
        $ministryIn = $ministryFolders ? implode(',', $ministryFolders) : '0';
        $ministryClause = Auth::isMedia() ? '' : " OR ({$alias}.visibility = 'ministerio' AND {$alias}.folder_id IN ({$ministryIn}))";

        $sql = "(
            {$alias}.uploaded_by = :acc_uid
            OR (
                {$alias}.status = 'aprovado' AND {$alias}.has_restriction = 0 AND (
                    ({$alias}.visibility IS NULL AND {$alias}.folder_id IN ({$folderIn}))
                    OR ({$alias}.visibility IN ({$allowedIn})){$ministryClause}
                )
            )
        )";
        return [$sql, ['acc_uid' => $uid]];
    }

    /** Cota do usuário em bytes (0 = ilimitada). */
    public static function quotaBytes(?string $role = null): int
    {
        $role ??= Auth::role();
        return (int) (QUOTA_GB[$role] ?? 0) * 1024 * 1024 * 1024;
    }

    public static function usedBytes(int $userId): int
    {
        return (int) Database::value("SELECT COALESCE(SUM(size_bytes),0) FROM files WHERE uploaded_by = :u AND status <> 'lixeira'", ['u' => $userId]);
    }
}
