<?php
// ============================================================
// INDEX.PHP — BỘ ĐỊNH TUYẾN CHÍNH
// ============================================================
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/json_helper.php';
require_once __DIR__ . '/helpers/user_helper.php';
require_once __DIR__ . '/helpers/category_helper.php';
require_once __DIR__ . '/controllers/auth_controller.php';
require_once __DIR__ . '/controllers/product_controller.php';
require_once __DIR__ . '/controllers/import_invoice_controller.php';

$page      = $_GET['page'] ?? 'dashboard';
$reqBranch = $_GET['branch'] ?? '';

// ============================================================
// AJAX ENDPOINTS
// ============================================================
if (!empty($_GET['ajax'])) {
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $ajax = $_GET['ajax'];

    if ($ajax === 'search_products') {
        $branch = $_GET['branch'] ?? '';
        $q      = $_GET['q'] ?? '';
        if ($branch && $q && canAccessBranch($branch)) {
            echo json_encode(searchProducts($branch, $q), JSON_UNESCAPED_UNICODE);
        } else {
            echo '[]';
        }
        exit;
    }

    if ($ajax === 'get_product') {
        $branch = $_GET['branch'] ?? '';
        $code   = $_GET['code'] ?? '';
        $p = $branch && $code ? productGetByCode($branch, $code) : null;
        echo json_encode($p, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['error' => 'Unknown endpoint']);
    exit;
}

// ============================================================
// LOGIN / LOGOUT
// ============================================================
if ($page === 'login') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = authLogin($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: index.php');
            exit;
        }
        $error = $result['message'];
    }
    include BASE_PATH . '/views/auth/login.php';
    exit;
}

if ($page === 'logout') {
    authLogout();
    exit;
}

// ============================================================
// REQUIRE LOGIN FOR ALL OTHER PAGES
// ============================================================
requireLogin();

// ============================================================
// PRODUCT ACTIONS
// ============================================================
if ($page === 'products') {
    $action = $_GET['action'] ?? '';
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        requireRole(['superadmin', 'admin']);
        $branch   = $_GET['branch'] ?? '';
        $category = $_POST['category'] ?? '';
        $result   = productSave($branch, $category, $_POST);
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        // Redirect về đúng nhóm vừa lưu
        $redirect = "index.php?page=products&branch={$branch}";
        if ($category) $redirect .= "&cat={$category}";
        header("Location: {$redirect}");
        exit;
    }
    if ($action === 'delete') {
        requireRole(['superadmin', 'admin']);
        $branch   = $_GET['branch'] ?? '';
        $id       = $_GET['id'] ?? '';
        $cat      = $_GET['cat'] ?? '';
        $result   = productDelete($branch, $cat, $id);
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        $redirect = "index.php?page=products&branch={$branch}";
        if ($cat) $redirect .= "&cat={$cat}";
        header("Location: {$redirect}");
        exit;
    }
}

// ============================================================
// IMPORT ACTION
// ============================================================
if ($page === 'imports' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    requireRole(['admin', 'warehouse']);
    $result = importProcess($_POST);
    $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger',
        'message' => $result['success'] ? "Nhập hàng thành công! Mã phiếu: {$result['id']}" : $result['message']];
    header("Location: index.php?page=imports&branch=" . urlencode($_POST['branch'] ?? ''));
    exit;
}

// ============================================================
// INVOICE ACTION
// ============================================================
if ($page === 'invoice' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_invoice') {
    requireRole(['superadmin', 'admin', 'sales']);
    $result = invoiceProcess($_POST);
    if ($result['success']) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Xuất hóa đơn thành công! Mã: {$result['id']} — Tổng: " . formatMoney($result['total'])];
        header("Location: index.php?page=invoices&branch=" . urlencode($_POST['branch'] ?? ''));
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
        header("Location: index.php?page=invoice&branch=" . urlencode($_POST['branch'] ?? ''));
    }
    exit;
}

// Đánh dấu đã giao hàng
if ($page === 'invoices' && ($_GET['action'] ?? '') === 'delivered') {
    requireRole(['superadmin', 'admin', 'sales']);
    $branch = $_GET['branch'] ?? '';
    $invId  = $_GET['id']     ?? '';
    $ym     = $_GET['ym']     ?? date('Y_m');
    $result = updateDeliveryStatus($branch, $invId, 'delivered');
    $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
    header("Location: index.php?page=invoices&branch={$branch}&ym={$ym}");
    exit;
}

// ============================================================
// CATEGORIES MANAGEMENT ACTIONS
// ============================================================
if ($page === 'categories') {
    requireRole(['superadmin', 'admin']);
    $action    = $_GET['action'] ?? '';
    $branchCat = $_GET['branch'] ?? array_key_first(BRANCHES);

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = saveCategory($branchCat, $_POST);
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        header("Location: index.php?page=categories&branch={$branchCat}"); exit;
    }
    if ($action === 'toggle') {
        $cat = getCategoryByKey($branchCat, $_GET['key'] ?? '');
        if ($cat) {
            $cat['active'] = !($cat['active'] ?? true);
            $cat['original_key'] = $cat['key'];
            saveCategory($branchCat, $cat);
            $status = $cat['active'] ? 'hiển thị' : 'ẩn';
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Đã {$status} nhóm '{$cat['name']}'"];
        }
        header("Location: index.php?page=categories&branch={$branchCat}"); exit;
    }
    if ($action === 'delete') {
        $result = deleteCategory($branchCat, $_GET['key'] ?? '');
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        header("Location: index.php?page=categories&branch={$branchCat}"); exit;
    }
}

// ============================================================
// USERS MANAGEMENT ACTIONS
// ============================================================
if ($page === 'users') {
    requireRole(['superadmin', 'admin']);
    $action = $_GET['action'] ?? '';

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $type = $_POST['action_type'] ?? 'add';
        if ($type === 'add') {
            $pwd  = $_POST['password'] ?? '';
            $pwd2 = $_POST['password_confirm'] ?? '';
            if ($pwd !== $pwd2) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Mật khẩu xác nhận không khớp'];
                header('Location: index.php?page=users'); exit;
            }
        }
        $result = saveUser($_POST);
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        header('Location: index.php?page=users'); exit;
    }

    if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $un   = $_POST['username'] ?? '';
        $pwd  = $_POST['new_password'] ?? '';
        $pwd2 = $_POST['confirm_password'] ?? '';
        if ($pwd !== $pwd2) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Mật khẩu xác nhận không khớp'];
            header('Location: index.php?page=users'); exit;
        }
        $result = resetPassword($un, $pwd);
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        header('Location: index.php?page=users'); exit;
    }

    if ($action === 'toggle') {
        $result = toggleUserActive($_GET['username'] ?? '');
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        header('Location: index.php?page=users'); exit;
    }

    if ($action === 'delete') {
        $result = deleteUser($_GET['username'] ?? '');
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
        header('Location: index.php?page=users'); exit;
    }
}

// ============================================================
// RENDER VIEWS
// ============================================================
$viewMap = [
    'dashboard' => BASE_PATH . '/views/dashboard/index.php',
    'products'  => BASE_PATH . '/views/products/index.php',
    'imports'   => BASE_PATH . '/views/imports/index.php',
    'invoice'   => BASE_PATH . '/views/invoices/create.php',
    'invoices'  => BASE_PATH . '/views/invoices/list.php',
    'reports'   => BASE_PATH . '/views/reports/index.php',
    'users'     => BASE_PATH . '/views/users/index.php',
    'categories'=> BASE_PATH . '/views/categories/index.php',
    'help'      => BASE_PATH . '/views/help/index.php',
];

$viewFile = $viewMap[$page] ?? null;

if (!$viewFile || !file_exists($viewFile)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => "Trang '{$page}' không tồn tại"];
    header('Location: index.php');
    exit;
}

// Access control per page
$salesOnly     = ['invoice', 'invoices'];
$warehouseOnly = ['imports'];
if (in_array($page, $salesOnly))     requireRole(['superadmin', 'admin', 'sales']);
if (in_array($page, $warehouseOnly)) requireRole(['superadmin', 'admin', 'warehouse']);

// Branch access check
if ($reqBranch && !canAccessBranch($reqBranch)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền truy cập chi nhánh này'];
    header('Location: index.php');
    exit;
}

include $viewFile;
