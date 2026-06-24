<?php

define('BRANCHES_FILE', DATA_PATH . '/branches.json');

function getDefaultBranches(): array
{
    return BRANCHES;
}

function getBranches(): array
{
    $defaults = array_values(getDefaultBranches());
    $saved = array_values(readJson(BRANCHES_FILE));

    if (!$saved) {
        $initial = [];
        foreach ($defaults as $branch) {
            $id = $branch['id'];
            $initial[] = array_merge($branch, ['id' => $id, 'base_id' => $id]);
        }
        writeJson(BRANCHES_FILE, $initial);
        return array_column($initial, null, 'id');
    }

    $branches = [];
    foreach ($defaults as $idx => $default) {
        $baseId = $default['id'];
        $custom = null;

        foreach ($saved as $row) {
            if (($row['base_id'] ?? $row['id'] ?? '') === $baseId) {
                $custom = $row;
                break;
            }
        }
        if (!$custom && isset($saved[$idx]) && is_array($saved[$idx])) {
            $custom = $saved[$idx];
        }

        $branch = array_merge($default, is_array($custom) ? $custom : []);
        $branch['base_id'] = $baseId;
        $branch['id'] = $branch['id'] ?? $baseId;
        $branches[$branch['id']] = $branch;
    }

    return $branches;
}

function getBranchInfo(string $branch): array
{
    $branches = getBranches();
    return $branches[$branch] ?? [
        'id' => $branch,
        'base_id' => $branch,
        'name' => $branch,
        'short' => strtoupper(substr($branch, 0, 3)),
        'icon' => 'bi-shop',
        'color' => 'secondary',
    ];
}

function firstBranchId(): string
{
    $branches = getBranches();
    return array_key_first($branches) ?? '';
}

function saveBranchesSettings(array $post): array
{
    $branches = array_values(getBranches());
    $names = $post['branch_name'] ?? [];
    $shorts = $post['branch_short'] ?? [];
    $savedBranches = [];
    $renamed = [];

    foreach ($branches as $idx => $branch) {
        $oldId = $branch['id'];
        $name = trim($names[$oldId] ?? $branch['name'] ?? '');
        $short = trim($shorts[$oldId] ?? $branch['short'] ?? '');

        if ($name === '') {
            return ['success' => false, 'message' => 'Tên chi nhánh không được để trống'];
        }
        if ($short === '') {
            return ['success' => false, 'message' => 'Tên viết tắt chi nhánh không được để trống'];
        }

        $short = mb_strtoupper($short, 'UTF-8');
        $newId = buildBranchId($idx + 1, $short);
        if (isset($savedBranches[$newId]) && $newId !== $oldId) {
            return ['success' => false, 'message' => "Mã chi nhánh '{$newId}' bị trùng. Vui lòng đổi tên viết tắt."];
        }

        if ($newId !== $oldId) {
            $migrate = migrateBranchId($oldId, $newId);
            if (!$migrate['success']) {
                return $migrate;
            }
            $renamed[] = "{$oldId} -> {$newId}";
        }

        $branch['id'] = $newId;
        $branch['base_id'] = $branch['base_id'] ?? $oldId;
        $branch['name'] = $name;
        $branch['short'] = $short;
        $savedBranches[$newId] = $branch;
    }

    $ok = writeJson(BRANCHES_FILE, array_values($savedBranches));
    $message = 'Đã cập nhật cấu hình chi nhánh';
    if ($renamed) {
        $message .= '. Đã đổi mã: ' . implode(', ', $renamed);
    }

    return $ok
        ? ['success' => true, 'message' => $message]
        : ['success' => false, 'message' => 'Không lưu được cấu hình chi nhánh'];
}

function buildBranchId(int $position, string $short): string
{
    return 'branch_' . $position . '_' . branchSlug($short);
}

function branchSlug(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($converted) && $converted !== '') {
        $value = strtolower($converted);
    }
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = trim($value ?? '', '_');
    return $value !== '' ? $value : 'cn';
}

function migrateBranchId(string $oldId, string $newId): array
{
    $oldDir = DATA_PATH . "/{$oldId}";
    $newDir = DATA_PATH . "/{$newId}";

    if (is_dir($newDir) && is_dir($oldDir) && $newDir !== $oldDir) {
        return ['success' => false, 'message' => "Thư mục dữ liệu '{$newId}' đã tồn tại. Vui lòng kiểm tra trước khi đổi mã chi nhánh."];
    }
    if (is_dir($oldDir) && !@rename($oldDir, $newDir)) {
        return ['success' => false, 'message' => "Không thể đổi thư mục dữ liệu từ '{$oldId}' sang '{$newId}'"];
    }
    if (!is_dir($oldDir) && !is_dir($newDir)) {
        mkdir($newDir, 0755, true);
    }

    if (defined('USERS_FILE')) {
        migrateBranchReferences(USERS_FILE, $oldId, $newId);
    }
    if (is_dir($newDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($newDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
                migrateBranchReferences($file->getPathname(), $oldId, $newId);
            }
        }
    }

    return ['success' => true];
}

function migrateBranchReferences(string $file, string $oldId, string $newId): void
{
    $data = readJson($file);
    if (!$data) return;

    $changed = false;
    $data = replaceBranchReference($data, $oldId, $newId, $changed);
    if ($changed) {
        writeJson($file, $data);
    }
}

function replaceBranchReference($value, string $oldId, string $newId, bool &$changed)
{
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            $value[$key] = replaceBranchReference($child, $oldId, $newId, $changed);
        }
        return $value;
    }

    if ($value === $oldId) {
        $changed = true;
        return $newId;
    }

    return $value;
}
