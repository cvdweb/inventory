<?php
// ============================================================
// CRON BACKUP — chạy qua cPanel Cron Jobs
// Cách dùng: php /path/to/cron_backup.php [daily|weekly|manual]
// ============================================================

define('BASE_PATH', __DIR__);
define('DATA_PATH', BASE_PATH . '/data');

$type       = $argv[1] ?? 'manual';  // daily | weekly | manual
$backupPath = BASE_PATH . '/backups';

if (!is_dir($backupPath)) mkdir($backupPath, 0755, true);

// Tên file backup
$timestamp  = date('Y-m-d_H-i-s');
$filename   = "backup_{$type}_{$timestamp}.zip";
$targetFile = $backupPath . '/' . $filename;

// Tạo ZIP
$zip = new ZipArchive();
if ($zip->open($targetFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "[ERROR] Không thể tạo file ZIP: {$targetFile}\n";
    exit(1);
}

// Thêm toàn bộ thư mục /data vào ZIP
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(DATA_PATH, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $filePath   = $file->getRealPath();
    $relativePath = substr($filePath, strlen(BASE_PATH) + 1);
    $zip->addFile($filePath, $relativePath);
    $count++;
}
$zip->close();

$size = filesize($targetFile);
echo "[OK] Backup tạo thành công: {$filename} ({$count} files, " . round($size/1024,1) . " KB)\n";
echo "[OK] Thời gian: " . date('Y-m-d H:i:s') . "\n";

// Dọn dẹp file cũ
$limits = ['daily' => 30, 'weekly' => 12, 'manual' => 50];
$limit  = $limits[$type] ?? 30;
$files  = glob($backupPath . "/backup_{$type}_*.zip") ?: [];
usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
if (count($files) > $limit) {
    $toDelete = array_slice($files, $limit);
    foreach ($toDelete as $f) {
        unlink($f);
        echo "[CLEAN] Đã xóa file cũ: " . basename($f) . "\n";
    }
}
