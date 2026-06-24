<?php

function backupDir(): string
{
    $dir = PRIVATE_BACKUP_PATH;
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    backupMigrateLegacyFiles($dir);
    return $dir;
}

function backupMigrateLegacyFiles(string $targetDir): void
{
    static $migrated = false;
    if ($migrated) return;
    $migrated = true;

    $legacyDir = BASE_PATH . '/backups';
    if (!is_dir($legacyDir)) return;

    foreach (glob($legacyDir . '/backup_*.zip') ?: [] as $source) {
        $target = $targetDir . '/' . basename($source);
        if (is_file($target)) {
            if (filesize($source) === filesize($target) && hash_file('sha256', $source) === hash_file('sha256', $target)) {
                @unlink($source);
            }
            continue;
        }
        if (!@rename($source, $target) && @copy($source, $target)) {
            @unlink($source);
        }
    }
}

function backupCreateZip(string $type = 'manual'): array
{
    $backupPath = backupDir();
    $safeType = preg_replace('/[^a-z0-9_]+/i', '_', $type) ?: 'manual';
    $filename = "backup_{$safeType}_" . date('Y-m-d_H-i-s') . '.zip';
    $targetFile = $backupPath . '/' . $filename;

    $zip = new ZipArchive();
    if ($zip->open($targetFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['success' => false, 'message' => 'Không tạo được file backup'];
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(DATA_PATH, FilesystemIterator::SKIP_DOTS)
    );
    $fileCount = 0;
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $real = $file->getRealPath();
        $relative = 'data/' . str_replace('\\', '/', substr($real, strlen(DATA_PATH) + 1));
        $zip->addFile($real, $relative);
        $fileCount++;
    }
    $zip->close();

    return [
        'success' => true,
        'filename' => $filename,
        'path' => $targetFile,
        'size' => file_exists($targetFile) ? filesize($targetFile) : 0,
        'files' => $fileCount,
        'message' => "Đã tạo backup: {$filename}",
    ];
}

function backupSafeRmdir(string $dir): void
{
    $real = realpath($dir);
    if ($real === false) return;

    $allowedRoots = [
        realpath(backupDir()),
        realpath(sys_get_temp_dir()),
    ];
    $isAllowed = false;
    foreach ($allowedRoots as $root) {
        if ($root && str_starts_with($real, $root)) {
            $isAllowed = true;
            break;
        }
    }
    if (!$isAllowed) return;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
    }
    rmdir($real);
}

function backupNormalizeZipName(string $name): string
{
    return ltrim(str_replace('\\', '/', $name), '/');
}

function backupValidateZip(string $zipPath): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['success' => false, 'message' => 'Không mở được file ZIP'];
    }

    $jsonFiles = 0;
    $dataEntries = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = backupNormalizeZipName($stat['name'] ?? '');
        if ($name === '' || str_ends_with($name, '/')) continue;

        if (
            str_contains($name, '../') ||
            str_contains($name, '/..') ||
            preg_match('/^[a-z]:/i', $name) ||
            !str_starts_with($name, 'data/')
        ) {
            $zip->close();
            return ['success' => false, 'message' => "File ZIP chứa đường dẫn không hợp lệ: {$name}"];
        }

        $dataEntries++;
        if (str_ends_with(strtolower($name), '.json')) {
            $jsonFiles++;
            $content = $zip->getFromIndex($i);
            if ($content === false || json_decode($content, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                $zip->close();
                return ['success' => false, 'message' => "JSON không hợp lệ trong file: {$name}"];
            }
        }
    }
    $zip->close();

    if ($dataEntries === 0) {
        return ['success' => false, 'message' => 'File ZIP không có thư mục data/'];
    }
    if ($jsonFiles === 0) {
        return ['success' => false, 'message' => 'File ZIP không có file JSON dữ liệu'];
    }

    return ['success' => true, 'entries' => $dataEntries, 'json_files' => $jsonFiles];
}

function backupExtractDataZip(string $zipPath, string $targetDir): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['success' => false, 'message' => 'Không mở được file ZIP'];
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = backupNormalizeZipName($stat['name'] ?? '');
        if ($name === '' || str_ends_with($name, '/')) continue;

        $dest = $targetDir . '/' . $name;
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $content = $zip->getFromIndex($i);
        if ($content === false || file_put_contents($dest, $content) === false) {
            $zip->close();
            return ['success' => false, 'message' => "Không giải nén được: {$name}"];
        }
    }
    $zip->close();
    return ['success' => true];
}

function backupRestoreFromZip(string $zipPath): array
{
    $validation = backupValidateZip($zipPath);
    if (!$validation['success']) {
        return $validation;
    }

    $preBackup = backupCreateZip('before_restore');
    if (!$preBackup['success']) {
        return ['success' => false, 'message' => 'Không thể tạo backup an toàn trước khi phục hồi: ' . $preBackup['message']];
    }

    $token = date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(3));
    $workDir = backupDir() . "/restore_tmp_{$token}";
    $oldDataDir = backupDir() . "/data_before_restore_{$token}";

    $extract = backupExtractDataZip($zipPath, $workDir);
    if (!$extract['success']) {
        backupSafeRmdir($workDir);
        return $extract;
    }

    $newDataDir = $workDir . '/data';
    if (!is_dir($newDataDir)) {
        backupSafeRmdir($workDir);
        return ['success' => false, 'message' => 'File ZIP không có thư mục data/ hợp lệ'];
    }

    if (!rename(DATA_PATH, $oldDataDir)) {
        backupSafeRmdir($workDir);
        return ['success' => false, 'message' => 'Không thể tạm di chuyển dữ liệu hiện tại'];
    }

    if (!rename($newDataDir, DATA_PATH)) {
        rename($oldDataDir, DATA_PATH);
        backupSafeRmdir($workDir);
        return ['success' => false, 'message' => 'Không thể đưa dữ liệu phục hồi vào hệ thống. Dữ liệu cũ đã được giữ lại.'];
    }

    backupSafeRmdir($workDir);
    backupSafeRmdir($oldDataDir);

    return [
        'success' => true,
        'message' => 'Phục hồi dữ liệu thành công. Backup an toàn trước phục hồi: ' . ($preBackup['filename'] ?? ''),
        'pre_backup' => $preBackup['filename'] ?? '',
        'entries' => $validation['entries'] ?? 0,
        'json_files' => $validation['json_files'] ?? 0,
    ];
}
