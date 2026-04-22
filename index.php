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
// BACKUP ACTIONS
// ============================================================
if ($page === 'backup') {
    requireRole(['superadmin', 'admin']);
    $action     = $_GET['action'] ?? '';
    $backupPath = BASE_PATH . '/backups';
    if (!is_dir($backupPath)) mkdir($backupPath, 0755, true);

    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $timestamp  = date('Y-m-d_H-i-s');
        $filename   = "backup_manual_{$timestamp}.zip";
        $targetFile = $backupPath . '/' . $filename;
        $zip = new ZipArchive();
        if ($zip->open($targetFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DATA_PATH, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $zip->addFile($file->getRealPath(), substr($file->getRealPath(), strlen(BASE_PATH)+1));
            }
            $zip->close();
            $_SESSION['flash'] = ['type'=>'success','message'=>"Sao lưu thành công: {$filename} (" . round(filesize($targetFile)/1024,1) . " KB)"];
        } else {
            $_SESSION['flash'] = ['type'=>'danger','message'=>'Lỗi tạo file backup. Kiểm tra quyền thư mục /backups/'];
        }
        header('Location: index.php?page=backup'); exit;
    }

    if ($action === 'download') {
        $file = basename($_GET['file'] ?? '');
        $path = $backupPath . '/' . $file;
        if ($file && file_exists($path) && str_starts_with($file, 'backup_')) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path); exit;
        }
        $_SESSION['flash'] = ['type'=>'danger','message'=>'File không tồn tại'];
        header('Location: index.php?page=backup'); exit;
    }

    if ($action === 'delete') {
        $file = basename($_GET['file'] ?? '');
        $path = $backupPath . '/' . $file;
        if ($file && file_exists($path) && str_starts_with($file, 'backup_')) {
            unlink($path);
            $_SESSION['flash'] = ['type'=>'success','message'=>"Đã xóa: {$file}"];
        }
        header('Location: index.php?page=backup'); exit;
    }

    if ($action === 'cleanup') {
        $files = glob($backupPath . '/backup_*.zip') ?: [];
        usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
        $deleted = 0;
        foreach (array_slice($files, 10) as $f) { unlink($f); $deleted++; }
        $_SESSION['flash'] = ['type'=>'success','message'=>"Đã xóa {$deleted} file cũ, giữ lại 10 file mới nhất"];
        header('Location: index.php?page=backup'); exit;
    }
}

// ============================================================
// EDIT INVOICE ACTION
// ============================================================
if ($page === 'invoices' && ($_GET['action'] ?? '') === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['superadmin', 'admin']);
    $branch    = $_GET['branch'] ?? '';
    $invoiceId = $_GET['id']     ?? '';
    $ym        = $_GET['ym']     ?? date('Y_m');
    $result    = updateInvoice($branch, $invoiceId, $_POST);
    $_SESSION['flash'] = ['type'=>$result['success']?'success':'danger','message'=>$result['message']];
    header("Location: index.php?page=invoices&branch={$branch}&ym={$ym}"); exit;
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
    'backup'    => BASE_PATH . '/views/backup/index.php',
    'edit_invoice'    => BASE_PATH . '/views/invoices/edit.php',
    'search_invoices' => BASE_PATH . '/views/invoices/search.php',
];

$viewFile = $viewMap[$page] ?? null;

if (!$viewFile || !file_exists($viewFile)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => "Trang '{$page}' không tồn tại"];
    header('Location: index.php');
    exit;
}

// Access control per page
$salesOnly     = ['invoice', 'invoices', 'search_invoices', 'edit_invoice'];
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
