<?php
// ============================================================
// AUTH CONTROLLER — đọc tài khoản từ data/users.json
// ============================================================

function authAttemptsFile(): string
{
    $override = trim((string)getenv('TRUONGPHU_AUTH_ATTEMPTS_PATH'));
    return $override !== '' ? $override : DATA_PATH . '/login_attempts.json';
}

function authAttemptKey(string $username): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
    return hash('sha256', $ip . '|' . mb_strtolower(trim($username), 'UTF-8'));
}

function authThrottleStatus(string $username): array
{
    $row = readJson(authAttemptsFile())[authAttemptKey($username)] ?? [];
    $blockedUntil = (int)($row['blocked_until'] ?? 0);
    $remaining = max(0, $blockedUntil - time());
    return ['blocked' => $remaining > 0, 'remaining' => $remaining];
}

function authRecordFailure(string $username): void
{
    $key = authAttemptKey($username);
    $now = time();
    updateJson(authAttemptsFile(), function (array $rows) use ($key, $now): array {
        foreach ($rows as $rowKey => $row) {
            $lastAttempt = (int)($row['last_attempt'] ?? 0);
            $blockedUntil = (int)($row['blocked_until'] ?? 0);
            if ($lastAttempt < $now - (LOGIN_ATTEMPT_WINDOW * 2) && $blockedUntil < $now) unset($rows[$rowKey]);
        }

        $row = $rows[$key] ?? ['count' => 0, 'first_attempt' => $now, 'blocked_until' => 0];
        if ((int)($row['first_attempt'] ?? 0) < $now - LOGIN_ATTEMPT_WINDOW) {
            $row = ['count' => 0, 'first_attempt' => $now, 'blocked_until' => 0];
        }
        $row['count'] = (int)($row['count'] ?? 0) + 1;
        $row['last_attempt'] = $now;
        if ($row['count'] >= LOGIN_MAX_ATTEMPTS) $row['blocked_until'] = $now + LOGIN_LOCK_SECONDS;
        $rows[$key] = $row;
        return $rows;
    });
}

function authClearFailures(string $username): void
{
    $key = authAttemptKey($username);
    updateJson(authAttemptsFile(), function (array $rows) use ($key): array {
        unset($rows[$key]);
        return $rows;
    });
}

function authLogin(string $username, string $password): array
{
    $username = trim($username);
    $throttle = authThrottleStatus($username);
    if ($throttle['blocked']) {
        $minutes = max(1, (int)ceil($throttle['remaining'] / 60));
        return ['success' => false, 'message' => "Đăng nhập tạm khóa. Vui lòng thử lại sau {$minutes} phút"];
    }

    $users = getAllUsers();
    $user  = null;
    foreach ($users as &$u) {
        if ($u['username'] === $username) { $user = &$u; break; }
    }
    unset($u);

    if (!$user) {
        authRecordFailure($username);
        return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'];
    }
    if (!($user['active'] ?? true)) {
        return ['success' => false, 'message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên'];
    }
    if (!verifyPassword($password, $user['password'] ?? '')) {
        authRecordFailure($username);
        return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'];
    }

    if (passwordNeedsUpgrade($user['password'] ?? '')) {
        $user['password'] = hashPassword($password);
        $user['updated_at'] = date('Y-m-d H:i:s');
        writeJson(USERS_FILE, $users);
    }

    session_regenerate_id(true);
    authClearFailures($username);
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
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
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

function passwordValidationError(string $password): string
{
    if (mb_strlen($password, 'UTF-8') < PASSWORD_MIN_LENGTH) {
        return 'Mật khẩu phải có ít nhất ' . PASSWORD_MIN_LENGTH . ' ký tự';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Mật khẩu phải có ít nhất một chữ và một số';
    }
    return '';
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
    // Đồng bộ quyền mới nhất để thay đổi vai trò/khóa tài khoản có hiệu lực ngay.
    $freshUser = getUserByUsername((string)$_SESSION['user']);
    if (!$freshUser || !($freshUser['active'] ?? true)) {
        authLogout();
    }
    $_SESSION['user_info'] = $freshUser;
    $_SESSION['login_time'] = time();
}

function requireRole(array $roles): void
{
    requireLogin();
    $role = $_SESSION['user_info']['role'] ?? '';
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
    if ($role === 'superadmin') return true;
    if ($role === 'admin') {
        $allowed = getUserBranches();
        if (empty($allowed)) return true;
        return in_array($branch, $allowed, true);
    }
    return in_array($branch, getUserBranches(), true);
}

function getAccessibleBranches(): array
{
    $user     = currentUser();
    $branches = getBranches();
    $role     = $user['role'] ?? '';
    if ($role === 'superadmin') return $branches;

    $allowed = getUserBranches();
    if ($role === 'admin' && empty($allowed)) return $branches;

    if (empty($allowed)) return [];
    return array_filter($branches, fn($b) => in_array($b['id'], $allowed, true));
}

function firstAccessibleBranchId(): string
{
    $branches = getAccessibleBranches();
    return array_key_first($branches) ?? '';
}

function isSuperAdmin(): bool { return (currentUser()['role'] ?? '') === 'superadmin'; }
function canManageUsers(): bool { return in_array(currentUser()['role'] ?? '', ['superadmin', 'admin'], true); }
function canViewBusinessData(): bool { return in_array(currentUser()['role'] ?? '', ['superadmin', 'admin', 'employee'], true); }
