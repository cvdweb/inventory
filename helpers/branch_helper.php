<?php

define('BRANCHES_FILE', DATA_PATH . '/branches.json');

function getDefaultBranches(): array
{
    return BRANCHES;
}

function getBranches(): array
{
    $saved = array_values(readJson(BRANCHES_FILE));

    if (!$saved) {
        $defaults = array_values(getDefaultBranches());
        $initial = [];
        foreach ($defaults as $branch) {
            $id = $branch['id'];
            $initial[] = array_merge($branch, ['id' => $id, 'base_id' => $id]);
        }
        writeJson(BRANCHES_FILE, $initial);
        return array_column($initial, null, 'id');
    }

    $branches = [];
    foreach ($saved as $branch) {
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

function saveBranch(array $post): array
{
    $branches = getBranches();
    $idEdit = $post['id_edit'] ?? '';
    $name = trim($post['name'] ?? '');
    $short = trim($post['short'] ?? '');
    $icon = trim($post['icon'] ?? 'bi-shop');
    $color = trim($post['color'] ?? 'primary');

    if ($name === '') return ['success' => false, 'message' => 'Tên chi nhánh không được để trống'];
    if ($short === '') return ['success' => false, 'message' => 'Tên viết tắt không được để trống'];

    $short = mb_strtoupper($short, 'UTF-8');
    
    if ($idEdit === '') {
        $position = count($branches) + 1;
        $newId = buildBranchId($position, $short);
        while (isset($branches[$newId])) {
            $position++;
            $newId = buildBranchId($position, $short);
        }
        $branch = [
            'id' => $newId,
            'base_id' => $newId,
            'name' => $name,
            'short' => $short,
            'icon' => $icon,
            'color' => $color
        ];
        $branches[$newId] = $branch;
        
        $dir = DATA_PATH . "/{$newId}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $message = 'Đã thêm chi nhánh mới';
    } else {
        if (!isset($branches[$idEdit])) return ['success' => false, 'message' => 'Không tìm thấy chi nhánh'];
        $branch = $branches[$idEdit];
        
        preg_match('/branch_(\d+)_/', $idEdit, $matches);
        $position = isset($matches[1]) ? (int)$matches[1] : count($branches);
        $newId = buildBranchId($position, $short);
        
        if (isset($branches[$newId]) && $newId !== $idEdit) {
             return ['success' => false, 'message' => "Mã chi nhánh '{$newId}' bị trùng. Vui lòng đổi tên viết tắt."];
        }
        
        if ($newId !== $idEdit) {
            $migrate = migrateBranchId($idEdit, $newId);
            if (!$migrate['success']) return $migrate;
            unset($branches[$idEdit]);
        }
        
        $branch['id'] = $newId;
        $branch['name'] = $name;
        $branch['short'] = $short;
        $branch['icon'] = $icon;
        $branch['color'] = $color;
        $branches[$newId] = $branch;
        $message = 'Đã cập nhật chi nhánh';
    }

    $ok = writeJson(BRANCHES_FILE, array_values($branches));
    return $ok
        ? ['success' => true, 'message' => $message]
        : ['success' => false, 'message' => 'Không lưu được cấu hình chi nhánh'];
}

function deleteBranch(string $id): array
{
    $branches = getBranches();
    if (!isset($branches[$id])) return ['success' => false, 'message' => 'Không tìm thấy chi nhánh'];
    if (count($branches) <= 1) return ['success' => false, 'message' => 'Phải giữ lại ít nhất 1 chi nhánh'];
    
    unset($branches[$id]);
    $ok = writeJson(BRANCHES_FILE, array_values($branches));
    
    $dir = DATA_PATH . "/{$id}";
    if (is_dir($dir)) {
        @rename($dir, $dir . '_deleted_' . time());
    }
    
    return $ok
        ? ['success' => true, 'message' => 'Đã xóa chi nhánh']
        : ['success' => false, 'message' => 'Không lưu được dữ liệu'];
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
