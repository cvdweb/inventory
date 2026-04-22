<?php
// ============================================================
// HELPER: ĐỌC / GHI NGƯỜI DÙNG (data/users.json)
// ============================================================

define('USERS_FILE', DATA_PATH . '/users.json');

// Các role hệ thống
define('ROLES', [
    'superadmin' => ['label' => 'Super Admin',  'icon' => 'bi-shield-fill-check', 'color' => 'danger'],
    'admin'      => ['label' => 'Quản trị',     'icon' => 'bi-shield-check',      'color' => 'warning'],
    'sales'      => ['label' => 'Bán hàng',     'icon' => 'bi-person-badge',      'color' => 'primary'],
    'warehouse'  => ['label' => 'Nhập hàng',    'icon' => 'bi-truck',             'color' => 'success'],
]);

/**
 * Lấy toàn bộ danh sách users
 */
function getAllUsers(): array
{
    return readJson(USERS_FILE);
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

    // Kiểm tra trùng username khi tạo mới
    if ($isNew) {
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
            'password'   => md5($userData['password']),
            'name'       => $userData['name'],
            'role'       => $userData['role'],
            'branch'     => normalizeBranch($userData['branch'] ?? null),
            'icon'       => iconByRole($userData['role']),
            'active'     => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $users[] = $newUser;
    } else {
        $found = false;
        foreach ($users as &$u) {
            if ($u['username'] === $origKey) {
                // Không cho đổi username của superadmin
                if ($u['role'] === 'superadmin' && $userData['role'] !== 'superadmin') {
                    return ['success' => false, 'message' => 'Không thể thay đổi role của Super Admin'];
                }
                $u['name']       = $userData['name'];
                $u['role']       = $userData['role'];
                $u['branch']     = normalizeBranch($userData['branch'] ?? null);
                $u['icon']       = iconByRole($userData['role']);
                $u['active']     = isset($userData['active']) ? (bool)$userData['active'] : true;
                $u['updated_at'] = $now;
                // Đổi mật khẩu chỉ khi có nhập
                if (!empty($userData['password'])) {
                    $u['password'] = md5($userData['password']);
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
function resetPassword(string $username, string $newPassword): array
{
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Mật khẩu phải ít nhất 6 ký tự'];
    }
    $users = getAllUsers();
    $found = false;
    foreach ($users as &$u) {
        if ($u['username'] === $username) {
            $u['password']   = md5($newPassword);
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
    $users = getAllUsers();
    foreach ($users as &$u) {
        if ($u['username'] === $username) {
            if ($u['role'] === 'superadmin') {
                return ['success' => false, 'message' => 'Không thể vô hiệu hóa tài khoản Super Admin'];
            }
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
    if ($user['role'] === 'superadmin') return ['success' => false, 'message' => 'Không thể xóa Super Admin'];

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

/**
 * Icon theo role
 */
function iconByRole(string $role): string
{
    return match($role) {
        'superadmin' => 'bi-shield-fill-check',
        'admin'      => 'bi-shield-check',
        'sales'      => 'bi-person-badge',
        'warehouse'  => 'bi-truck',
        default      => 'bi-person',
    };
}
