<?php
// Chạy qua cPanel Cron Jobs:
// php /path/to/truongphu/cron_backup.php [daily|weekly|manual]

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/backup_helper.php';

$type = strtolower(trim($argv[1] ?? 'manual'));
if (!in_array($type, ['daily', 'weekly', 'manual'], true)) {
    fwrite(STDERR, "[ERROR] Loại backup không hợp lệ. Dùng daily, weekly hoặc manual.\n");
    exit(1);
}

$result = backupCreateZip($type);
if (!($result['success'] ?? false)) {
    fwrite(STDERR, '[ERROR] ' . ($result['message'] ?? 'Không tạo được backup') . "\n");
    exit(1);
}

echo '[OK] Backup: ' . $result['filename']
    . ' (' . ($result['files'] ?? 0) . ' files, '
    . round(($result['size'] ?? 0) / 1024, 1) . " KB)\n";
echo '[OK] Kho riêng: ' . backupDir() . "\n";
echo '[OK] Thời gian: ' . date('Y-m-d H:i:s') . "\n";

$limits = ['daily' => 30, 'weekly' => 12, 'manual' => 50];
$files = glob(backupDir() . "/backup_{$type}_*.zip") ?: [];
usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
foreach (array_slice($files, $limits[$type]) as $file) {
    if (@unlink($file)) echo '[CLEAN] Đã xóa: ' . basename($file) . "\n";
}
