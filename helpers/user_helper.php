<?php
// ============================================================
// HELPER: ĐỌC / GHI NGƯỜI DÙNG (data/users.json)
// ============================================================

define('USERS_FILE', DATA_PATH . '/users.json');

// Các role hệ thống
define('ROLES', [
    'superadmin' => ['label' => 'Super Admin', 'icon' => 'bi-shield-fill-check', 'color' => 'danger',
                     'desc' => 'Quản trị hệ thống, giấy phép, dữ liệu và hỗ trợ kỹ thuật'],
    'admin'      => ['label' => 'Chủ Cửa Hàng', 'icon' => 'bi-star-fill', 'color' => 'warning',
                     'desc' => 'Toàn quyền kinh doanh, tất cả chi nhánh và quản lý nhân viên'],
    'employee'   => ['label' => 'Nhân Viên', 'icon' => 'bi-person-badge', 'color' => 'primary',
                     'desc' => 'Thao tác nghiệp vụ tại các chi nhánh được phân công'],
]);

/**
 * Lấy toàn bộ danh sách users
 */
function getAllUsers(): array
{
    $users = readJson(USERS_FILE);
    $changed = false;

    foreach ($users as &$user) {
        $oldRole = (string)($user['role'] ?? '');
        $branches = normalizeBranch($user['branch'] ?? null);
        $newRole = match ($oldRole) {
            'owner' => 'admin',
            'sales', 'warehouse' => 'employee',
            // Quản lý cũ có giới hạn chi nhánh không được tự động nâng thành chủ cửa hàng.
            'admin' => empty($branches) ? 'admin' : 'employee',
            'superadmin', 'employee' => $oldRole,
            default => 'employee',
        };
        $newBranches = in_array($newRole, ['superadmin', 'admin'], true) ? null : $branches;
        $newIcon = iconByRole($newRole);

        if ($oldRole !== $newRole || ($user['branch'] ?? null) !== $newBranches || ($user['icon'] ?? '') !== $newIcon) {
            $user['role'] = $newRole;
            $user['branch'] = $newBranches;
            $user['icon'] = $newIcon;
            $user['updated_at'] = date('Y-m-d H:i:s');
            $changed = true;
        }
    }
    unset($user);

    if ($changed) writeJson(USERS_FILE, $users);
    return $users;
}

function canCreateUserRole(string $role): bool
{
    $actorRole = currentUser()['role'] ?? '';
    if ($actorRole === 'superadmin') return in_array($role, ['admin', 'employee'], true);
    return $actorRole === 'admin' && $role === 'employee';
}

function canManageTargetUser(array $target): bool
{
    $actor = currentUser();
    $actorRole = $actor['role'] ?? '';
    $targetRole = $target['role'] ?? '';
    if (($target['username'] ?? '') === ($actor['username'] ?? '')) return false;
    if ($targetRole === 'superadmin') return false;
    if ($actorRole === 'superadmin') return true;
    return $actorRole === 'admin' && $targetRole === 'employee';
}

/**
 * Lấy user theo username
 */
function getUserByUsername(string $username): ?array
{
    foreach (getAllUsers() as $u) {
        if ($u['username'] === $username) return $u;
    }
    return null;
}

/**
 * Lưu / cập nhật user (dùng flock)
 */
function saveUser(array $userData): array
{
    $users   = getAllUsers();
    $isNew   = empty($userData['id_edit']); // id_edit = username gốc khi edit
    $origKey = $userData['id_edit'] ?? $userData['username'];
    $role    = $userData['role'] ?? '';
    $branch  = normalizeBranch($userData['branch'] ?? null);

    if (!isset(ROLES[$role])) {
        return ['success' => false, 'message' => 'Vai trò không hợp lệ'];
    }
    if (!$isNew) {
        $target = getUserByUsername($origKey);
        if (!$target || !canManageTargetUser($target)) {
            return ['success' => false, 'message' => 'Bạn không có quyền sửa tài khoản này'];
        }
    } elseif (!canCreateUserRole($role)) {
        return ['success' => false, 'message' => 'Bạn không có quyền tạo tài khoản với vai trò này'];
    }
    if ($role === 'employee' && empty($branch)) {
        return ['success' => false, 'message' => 'Vui lòng chọn ít nhất một chi nhánh cho tài khoản này'];
    }
    if ($role === 'admin') {
        $branch = null;
    }

    // Kiểm tra trùng username khi tạo mới
    if (!$isNew && !empty($userData['password'])) {
        $passwordError = passwordValidationError((string)$userData['password']);
        if ($passwordError !== '') return ['success' => false, 'message' => $passwordError];
    }

    if ($isNew) {
        $passwordError = passwordValidationError((string)($userData['password'] ?? ''));
        if ($passwordError !== '') return ['success' => false, 'message' => $passwordError];
        foreach ($users as $u) {
            if ($u['username'] === $userData['username']) {
                return ['success' => false, 'message' => "Tên đăng nhập '{$userData['username']}' đã tồn tại"];
            }
        }
    }

    $now = date('Y-m-d H:i:s');

    if ($isNew) {
        $newUser = [
            'username'   => $userData['username'],
            'password'   => hashPassword($userData['password']),
            'name'       => $userData['name'],
            'role'       => $role,
            'branch'     => $branch,
            'icon'       => iconByRole($role),
            'active'     => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $users[] = $newUser;
    } else {
        $found = false;
        foreach ($users as &$u) {
            if ($u['username'] === $origKey) {
                $u['name']       = $userData['name'];
                $u['role']       = $role;
                $u['branch']     = $branch;
                $u['icon']       = iconByRole($role);
                $u['active']     = isset($userData['active']) ? (bool)$userData['active'] : true;
                $u['updated_at'] = $now;
                // Đổi mật khẩu chỉ khi có nhập
                if (!empty($userData['password'])) {
                    $u['password'] = hashPassword($userData['password']);
                }
                $found = true;
                break;
            }
        }
        if (!$found) return ['success' => false, 'message' => 'Không tìm thấy người dùng'];
    }

    $ok = writeJson(USERS_FILE, $users);
    return $ok
        ? ['success' => true, 'message' => $isNew ? 'Tạo tài khoản thành công' : 'Cập nhật thành công']
        : ['success' => false, 'message' => 'Lỗi ghi file'];
}

/**
 * Reset mật khẩu
 */
function resetPassword(string $username, string $newPassword, bool $allowSelf = false): array
{
    $passwordError = passwordValidationError($newPassword);
    if ($passwordError !== '') return ['success' => false, 'message' => $passwordError];
    $target = getUserByUsername($username);
    $isSelf = $target && ($target['username'] ?? '') === (currentUser()['username'] ?? '');
    if (!$target || !($allowSelf && $isSelf) && !canManageTargetUser($target)) {
        return ['success' => false, 'message' => 'Bạn không có quyền đặt lại mật khẩu tài khoản này'];
    }
    $users = getAllUsers();
    $found = false;
    foreach ($users as &$u) {
        if ($u['username'] === $username) {
            $u['password']   = hashPassword($newPassword);
            $u['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    if (!$found) return ['success' => false, 'message' => 'Không tìm thấy người dùng'];
    $ok = writeJson(USERS_FILE, $users);
    return $ok
        ? ['success' => true, 'message' => "Đã reset mật khẩu cho tài khoản '{$username}'"]
        : ['success' => false, 'message' => 'Lỗi ghi file'];
}

/**
 * Bật / tắt tài khoản
 */
function toggleUserActive(string $username): array
{
    $target = getUserByUsername($username);
    if (!$target || !canManageTargetUser($target)) {
        return ['success' => false, 'message' => 'Bạn không có quyền thay đổi trạng thái tài khoản này'];
    }
    $users = getAllUsers();
    foreach ($users as &$u) {
        if ($u['username'] === $username) {
            $u['active']     = !($u['active'] ?? true);
            $u['updated_at'] = date('Y-m-d H:i:s');
            $status = $u['active'] ? 'kích hoạt' : 'vô hiệu hóa';
            writeJson(USERS_FILE, $users);
            return ['success' => true, 'message' => "Đã {$status} tài khoản '{$username}'"];
        }
    }
    return ['success' => false, 'message' => 'Không tìm thấy người dùng'];
}

/**
 * Xóa tài khoản
 */
function deleteUser(string $username): array
{
    $user = getUserByUsername($username);
    if (!$user) return ['success' => false, 'message' => 'Không tìm thấy người dùng'];
    if (!canManageTargetUser($user)) return ['success' => false, 'message' => 'Bạn không có quyền xóa tài khoản này'];

    $users = array_values(array_filter(getAllUsers(), fn($u) => $u['username'] !== $username));
    $ok    = writeJson(USERS_FILE, $users);
    return $ok
        ? ['success' => true, 'message' => "Đã xóa tài khoản '{$username}'"]
        : ['success' => false, 'message' => 'Lỗi ghi file'];
}

/**
 * Chuẩn hóa branch: luôn lưu dạng array (hoặc null nếu rỗng)
 * Input: string | array | null
 */
function normalizeBranch($branch): ?array
{
    if (empty($branch)) return null;
    if (is_string($branch)) {
        // Có thể là JSON array từ form multi-select hoặc string đơn
        $decoded = json_decode($branch, true);
        if (is_array($decoded)) $branch = $decoded;
        else $branch = [$branch];
    }
    $filtered = array_values(array_filter((array)$branch, fn($b) => !empty($b)));
    return empty($filtered) ? null : $filtered;
}

function branchesOverlap($a, array $b): bool
{
    $left = normalizeBranch($a) ?? [];
    return !empty(array_intersect($left, $b));
}

/**
 * Icon theo role
 */
function iconByRole(string $role): string
{
    return match($role) {
        'superadmin' => 'bi-shield-fill-check',
        'admin'      => 'bi-star-fill',
        'employee'   => 'bi-person-badge',
        default      => 'bi-person',
    };
}
