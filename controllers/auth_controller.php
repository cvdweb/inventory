<?php
// ============================================================
// AUTH CONTROLLER — đọc tài khoản từ data/users.json
// ============================================================

function authLogin(string $username, string $password): array
{
    $users = getAllUsers();
    $user  = null;
    foreach ($users as &$u) {
        if ($u['username'] === $username) { $user = &$u; break; }
    }
    unset($u);

    if (!$user) {
        return ['success' => false, 'message' => 'Tên đăng nhập không tồn tại'];
    }
    if (!($user['active'] ?? true)) {
        return ['success' => false, 'message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên'];
    }
    if (!verifyPassword($password, $user['password'] ?? '')) {
        return ['success' => false, 'message' => 'Mật khẩu không đúng'];
    }

    if (passwordNeedsUpgrade($user['password'] ?? '')) {
        $user['password'] = hashPassword($password);
        $user['updated_at'] = date('Y-m-d H:i:s');
        writeJson(USERS_FILE, $users);
    }

    session_regenerate_id(true);
    $_SESSION['user']       = $username;
    $_SESSION['user_info']  = $user;
    $_SESSION['login_time'] = time();
    return ['success' => true, 'user' => $user];
}

function authLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword(string $password, string $storedHash): bool
{
    if ($storedHash === '') return false;
    $info = password_get_info($storedHash);
    if (($info['algo'] ?? 0) !== 0) {
        return password_verify($password, $storedHash);
    }
    return hash_equals($storedHash, md5($password));
}

function passwordNeedsUpgrade(string $storedHash): bool
{
    $info = password_get_info($storedHash);
    return ($info['algo'] ?? 0) === 0 || password_needs_rehash($storedHash, PASSWORD_DEFAULT);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function requireValidCsrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $valid = is_string($sent) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
    if ($valid) return;

    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng thử lại.'];
    $target = empty($_SESSION['user']) ? 'index.php?page=login' : 'index.php';
    header('Location: ' . $target);
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
    // owner có quyền tương đương admin về kinh doanh
    if ($role === 'owner' && in_array('admin', $roles)) return;
    // superadmin KHÔNG bypass mọi quyền nữa
    if ($role === 'superadmin') {
        if (in_array('superadmin', $roles)) return;
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Tài khoản kỹ thuật không có quyền truy cập dữ liệu kinh doanh'];
        header('Location: index.php'); exit;
    }
    if (!in_array($role, $roles)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này'];
        header('Location: index.php'); exit;
    }
}

function currentUser(): array
{
    return $_SESSION['user_info'] ?? [];
}

function getUserBranches(): array
{
    $branches = currentUser()['branch'] ?? [];
    if (is_string($branches)) {
        return $branches === '' ? [] : [$branches];
    }
    return is_array($branches) ? array_values(array_filter($branches, fn($b) => !empty($b))) : [];
}

function canAccessBranch(string $branch): bool
{
    $user = currentUser();
    $role = $user['role'] ?? '';
    if ($role === 'superadmin') return false;
    if (in_array($role, ['owner', 'admin', 'warehouse'])) return true;
    return in_array($branch, getUserBranches());
}

function getAccessibleBranches(): array
{
    $user     = currentUser();
    $branches = BRANCHES;
    $role     = $user['role'] ?? '';
    if ($role === 'superadmin') return [];
    if ($role === 'sales') {
        $allowed = getUserBranches();
        if (empty($allowed)) return [];
        return array_filter($branches, fn($b) => in_array($b['id'], $allowed));
    }
    return $branches;
}

function isSuperAdmin(): bool { return (currentUser()['role'] ?? '') === 'superadmin'; }
function isOwner(): bool { return (currentUser()['role'] ?? '') === 'owner'; }
function canManageUsers(): bool { return in_array(currentUser()['role'] ?? '', ['superadmin', 'owner', 'admin']); }
function canViewBusinessData(): bool { return in_array(currentUser()['role'] ?? '', ['owner', 'admin', 'sales', 'warehouse']); }
