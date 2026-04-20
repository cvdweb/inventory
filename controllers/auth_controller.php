<?php
// ============================================================
// AUTH CONTROLLER — đọc tài khoản từ data/users.json
// ============================================================

function authLogin(string $username, string $password): array
{
    $users = getAllUsers();
    $user  = null;
    foreach ($users as $u) {
        if ($u['username'] === $username) { $user = $u; break; }
    }

    if (!$user) {
        return ['success' => false, 'message' => 'Tên đăng nhập không tồn tại'];
    }
    if (!($user['active'] ?? true)) {
        return ['success' => false, 'message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên'];
    }
    if ($user['password'] !== md5($password)) {
        return ['success' => false, 'message' => 'Mật khẩu không đúng'];
    }

    $_SESSION['user']       = $username;
    $_SESSION['user_info']  = $user;
    $_SESSION['login_time'] = time();
    return ['success' => true, 'user' => $user];
}

function authLogout(): void
{
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

function requireLogin(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }
    if (time() - ($_SESSION['login_time'] ?? 0) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: index.php?page=login&timeout=1');
        exit;
    }
    $_SESSION['login_time'] = time();
}

function requireRole(array $roles): void
{
    requireLogin();
    $role = $_SESSION['user_info']['role'] ?? '';
    if ($role === 'superadmin') return;
    if (!in_array($role, $roles)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này'];
        header('Location: index.php');
        exit;
    }
}

function currentUser(): array
{
    return $_SESSION['user_info'] ?? [];
}

function currentBranch(): ?string
{
    return $_SESSION['user_info']['branch'] ?? null;
}

function canAccessBranch(string $branch): bool
{
    $user = currentUser();
    if (in_array($user['role'] ?? '', ['superadmin', 'admin', 'warehouse'])) return true;
    return ($user['branch'] ?? '') === $branch;
}

function getAccessibleBranches(): array
{
    $user     = currentUser();
    $branches = BRANCHES;
    if (($user['role'] ?? '') === 'sales' && !empty($user['branch'])) {
        return array_filter($branches, fn($b) => $b['id'] === $user['branch']);
    }
    return $branches;
}

function isSuperAdmin(): bool
{
    return (currentUser()['role'] ?? '') === 'superadmin';
}

function canManageUsers(): bool
{
    return in_array(currentUser()['role'] ?? '', ['superadmin', 'admin']);
}
