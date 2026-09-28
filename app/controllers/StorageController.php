<?php
// app/controllers/StorageController.php — painel de espaço usado
declare(strict_types=1);

final class StorageController
{
    public function index(): never
    {
        $totals = MediaFile::totals();
        $disk = Storage::driver()->usage();
        $limit = STORAGE_ALERT_GB * 1024 * 1024 * 1024;
        view('storage/index', [
            'title'      => 'Armazenamento',
            'totals'     => $totals,
            'disk'       => $disk,
            'diskTotal'  => array_sum($disk),
            'limit'      => $limit,
            'alert'      => $limit > 0 && array_sum($disk) > $limit,
            'byRoot'     => Folder::usageByRoot(),
            'byCategory' => MediaFile::usageByCategory(),
            'byUser'     => MediaFile::usageByUser(),
            'quarantine' => MediaFile::countQuarantine(),
            'trash'      => (int) Database::value("SELECT COALESCE(SUM(size_bytes),0) FROM files WHERE status = 'lixeira'"),
            'pending'    => (int) Database::value("SELECT COUNT(*) FROM upload_sessions WHERE status = 'aberto'"),
        ]);
    }
}
