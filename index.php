<?php
// ============================================================
// INDEX.PHP — BỘ ĐỊNH TUYẾN CHÍNH
// ============================================================
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$httpsEnabled = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
session_name('TRUONGPHU_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $httpsEnabled,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/json_helper.php';
require_once __DIR__ . '/helpers/feature_helper.php';
require_once __DIR__ . '/helpers/branch_helper.php';
require_once __DIR__ . '/helpers/user_helper.php';
require_once __DIR__ . '/helpers/category_helper.php';
require_once __DIR__ . '/helpers/backup_helper.php';
require_once __DIR__ . '/helpers/cashbook_helper.php';
require_once __DIR__ . '/helpers/sales_return_helper.php';
require_once __DIR__ . '/helpers/import_bulk_helper.php';
require_once __DIR__ . '/helpers/receivable_helper.php';
require_once __DIR__ . '/helpers/license_helper.php';
require_once __DIR__ . '/helpers/report_helper.php';
require_once __DIR__ . '/helpers/integrity_helper.php';
require_once __DIR__ . '/helpers/inventory_adjustment_helper.php';
require_once __DIR__ . '/controllers/auth_controller.php';
require_once __DIR__ . '/controllers/product_controller.php';
require_once __DIR__ . '/controllers/import_invoice_controller.php';

$page      = $_GET['page'] ?? 'dashboard';
$reqBranch = $_GET['branch'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
}

if (!empty($_GET['ajax'])) {
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['ajax'] === 'search_products') {
        $branch = $_GET['branch'] ?? ''; $q = $_GET['q'] ?? '';
        echo ($branch && $q && canAccessBranch($branch)) ? json_encode(searchProducts($branch,$q),JSON_UNESCAPED_UNICODE) : '[]';
    } else { echo json_encode(['error'=>'Unknown']); }
    exit;
}

// Superadmin sees system page, not business dashboard
if (isset($_SESSION['user_info']) && ($_SESSION['user_info']['role']??'') === 'superadmin'
    && $page === 'dashboard') {
    $page = 'superadmin_dashboard';
}

if ($page==='login') {
    $error='';
    if ($_SERVER['REQUEST_METHOD']=== 'POST') {
        $r=authLogin($_POST['username']??'',$_POST['password']??'');
        if($r['success']){header('Location: index.php');exit;}
        $error=$r['message'];
    }
    include BASE_PATH.'/views/auth/login.php'; exit;
}
if ($page==='logout'){authLogout();exit;}
requireLogin();
featureEnforcePage($page);

// Chặn truy cập chéo chi nhánh trước khi bất kỳ action nào được xử lý.
$actionBranch = $_GET['branch'] ?? $_POST['branch'] ?? '';
if ($actionBranch !== '' && !canAccessBranch($actionBranch)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Không có quyền truy cập chi nhánh này'];
    header('Location: index.php');
    exit;
}

$licenseAction = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'license') {
    licenseEnforceWriteAllowed($page, $licenseAction);
}

// LICENSE
if ($page==='license') {
    requireRole(['superadmin']);
    $action=$_GET['action']??'';
    if ($action==='settings_save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=licenseSaveSettings($_POST);
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=license'); exit;
    }
    if ($action==='payment_add' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=licenseAddPayment($_POST);
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=license'); exit;
    }
    if ($action==='payment_delete' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=licenseDeletePayment($_POST['payment_id']??'');
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=license'); exit;
    }
    if ($action==='lock_update' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=licenseUpdateLock($_POST);
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=license'); exit;
    }
    if ($action==='features_save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=featureSaveProfile((string)($_POST['feature_profile']??''));
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=license#featureProfileCard'); exit;
    }
}

// PROFILE
if ($page==='profile') {
    $action=$_GET['action']??''  ; $un=$_SESSION['user']??'';
    if ($action==='update_info' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $name=trim($_POST['name']??'');
        if(!$name){$_SESSION['profile_error']='Họ tên không được để trống';}
        else{
            $users=getAllUsers();
            foreach($users as &$u){if($u['username']===$un){$u['name']=$name;$u['updated_at']=date('Y-m-d H:i:s');break;}}
            if(writeJson(USERS_FILE,$users)){$_SESSION['user_info']['name']=$name;$_SESSION['profile_success']='Đã cập nhật thành công';}
            else{$_SESSION['profile_error']='Lỗi lưu thông tin';}
        }
        header('Location: index.php?page=profile'); exit;
    }
    if ($action==='change_password' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $cur=$_POST['current_password']??''; $np=$_POST['new_password']??''; $cp=$_POST['confirm_password']??'';
        $users=getAllUsers(); $cu=null;
        foreach($users as $u){if($u['username']===$un){$cu=$u;break;}}
        if(!$cu) $_SESSION['pwd_error']='Không tìm thấy tài khoản';
        elseif(!verifyPassword($cur, $cu['password'] ?? '')) $_SESSION['pwd_error']='Mật khẩu hiện tại không đúng';
        elseif(($passwordError=passwordValidationError($np))!=='') $_SESSION['pwd_error']=$passwordError;
        elseif($np!==$cp) $_SESSION['pwd_error']='Mật khẩu xác nhận không khớp';
        elseif(verifyPassword($np, $cu['password'] ?? '')) $_SESSION['pwd_error']='Mật khẩu mới phải khác mật khẩu cũ';
        else{$r=resetPassword($un,$np,true);$_SESSION[$r['success']?'pwd_success':'pwd_error']=$r['success']?'Đổi mật khẩu thành công!':$r['message'];}
        header('Location: index.php?page=profile'); exit;
    }
}

// PRODUCTS
if ($page==='products') {
    $action=$_GET['action']??'';
    if ($action==='bulk_template') {
        requireRole(['superadmin','admin']);
        featureRequire('bulk_import');
        productBulkTemplate($_GET['branch']??'', $_GET['cat']??'');
    }
    if ($action==='bulk_preview' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','admin']);
        featureRequire('bulk_import');
        $branch=$_GET['branch']??''; $cat=$_POST['category']??'';
        $r=productBulkPreview($branch,$cat,$_FILES['csv_file']??[]);
        if($r['success']){
            $_SESSION['product_bulk_preview']=$r['preview'];
            $_SESSION['flash']=['type'=>'success','message'=>'Đã đọc file CSV. Vui lòng kiểm tra preview trước khi xác nhận nhập.'];
        } else {
            unset($_SESSION['product_bulk_preview']);
            $_SESSION['flash']=['type'=>'danger','message'=>$r['message']];
        }
        header("Location: index.php?page=products&branch={$branch}".($cat?"&cat={$cat}":'')."&bulk_preview=1"); exit;
    }
    if ($action==='bulk_commit' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','admin']);
        featureRequire('bulk_import');
        $preview=$_SESSION['product_bulk_preview']??[];
        $r=withBranchTransaction($preview['branch']??'', fn()=>productBulkCommit($preview));
        unset($_SESSION['product_bulk_preview']);
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        $branch=$preview['branch']??($_GET['branch']??''); $cat=$preview['category']??'';
        header("Location: index.php?page=products&branch={$branch}".($cat?"&cat={$cat}":'')); exit;
    }
    if ($action==='bulk_cancel' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','admin']);
        unset($_SESSION['product_bulk_preview']);
        $branch=$_GET['branch']??'';
        header("Location: index.php?page=products&branch={$branch}"); exit;
    }
    if ($action==='save' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','admin']);
        $branch=$_GET['branch']??''; $cat=$_POST['category']??'';
        $r=withBranchTransaction($branch, fn()=>productSave($branch,$cat,$_POST));
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=products&branch={$branch}".($cat?"&cat={$cat}":'')); exit;
    }
    if ($action==='delete' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','admin']);
        $branch=$_GET['branch']??''; $id=$_GET['id']??''; $cat=$_GET['cat']??'';
        $r=withBranchTransaction($branch, fn()=>productDelete($branch,$cat,$id,$_POST['reason']??''));
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=products&branch={$branch}".($cat?"&cat={$cat}":'')); exit;
    }
    if ($action==='restore' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','admin']);
        $branch=$_GET['branch']??''; $id=$_GET['id']??''; $cat=$_GET['cat']??'';
        $r=withBranchTransaction($branch, fn()=>productRestore($branch,$cat,$id));
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=products&branch={$branch}&status=archived".($cat?"&cat={$cat}":'')); exit;
    }
}

// IMPORTS
if ($page==='imports') {
    $bulkAction=$_GET['action']??'';
    $bulkBranch=$_GET['branch']??firstAccessibleBranchId();
    if ($bulkAction==='bulk_template') {
        requireRole(['superadmin','admin','employee']);
        featureRequire('bulk_import');
        importBulkTemplate($bulkBranch);
    }
    if ($bulkAction==='bulk_preview' && $_SERVER['REQUEST_METHOD']==='POST') {
        requireRole(['superadmin','admin','employee']);
        featureRequire('bulk_import');
        $r=importBulkPreview($bulkBranch,$_POST,$_FILES['csv_file']??[]);
        if ($r['success']) {
            $_SESSION['import_bulk_preview']=$r['preview'];
            $_SESSION['flash']=['type'=>'success','message'=>'Đã đọc file CSV. Vui lòng kiểm tra trước khi xác nhận nhập kho.'];
        } else {
            unset($_SESSION['import_bulk_preview']);
            $_SESSION['flash']=['type'=>'danger','message'=>$r['message']];
        }
        header('Location: index.php?page=imports&branch='.urlencode($bulkBranch).'&bulk_preview=1'); exit;
    }
    if ($bulkAction==='bulk_commit' && $_SERVER['REQUEST_METHOD']==='POST') {
        requireRole(['superadmin','admin','employee']);
        featureRequire('bulk_import');
        $preview=$_SESSION['import_bulk_preview']??[];
        $r=withBranchTransaction($preview['branch']??'', fn()=>importBulkCommit($preview));
        if ($r['success']) unset($_SESSION['import_bulk_preview']);
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['success'] ? $r['message'].". {$r['count']} mặt hàng, tổng ".formatMoney((float)$r['total']) : $r['message']];
        header('Location: index.php?page=imports&branch='.urlencode($bulkBranch)); exit;
    }
    if ($bulkAction==='bulk_cancel' && $_SERVER['REQUEST_METHOD']==='POST') {
        requireRole(['superadmin','admin','employee']);
        unset($_SESSION['import_bulk_preview']);
        header('Location: index.php?page=imports&branch='.urlencode($bulkBranch)); exit;
    }
}

if ($page==='imports' && $_SERVER['REQUEST_METHOD']=== 'POST' && ($_POST['action']??'')=== 'import') {
    requireRole(['superadmin','admin','employee']);
    $r=withBranchTransaction($_POST['branch']??'', fn()=>importProcess($_POST));
    $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['success']?"Nhập hàng thành công! Mã: {$r['id']}":$r['message']];
    header("Location: index.php?page=imports&branch=".urlencode($_POST['branch']??'')); exit;
}

// INVOICES
if ($page==='invoice' && $_SERVER['REQUEST_METHOD']=== 'POST' && ($_POST['action']??'')=== 'create_invoice') {
    requireRole(['superadmin','admin','employee']);
    $r=withBranchTransaction($_POST['branch']??'', fn()=>invoiceProcess($_POST));
    if($r['success']){
        $_SESSION['flash']=['type'=>empty($r['warning'])?'success':'warning','message'=> "Xuất HĐ thành công! Mã: {$r['id']} — ".formatMoney($r['total']).(!empty($r['warning']) ? '. '.$r['warning'] : '')];
        header("Location: index.php?page=invoices&branch=".urlencode($_POST['branch']??'')."&ym=".date('Y_m')."&latest=".urlencode($r['id']));
    } else {
        $_SESSION['flash']=['type'=>'danger','message'=> $r['message']];
        header("Location: index.php?page=invoice&branch=".urlencode($_POST['branch']??'')); 
    }
    exit;
}
if ($page==='invoices' && ($_GET['action']??'')=== 'delivered' && $_SERVER['REQUEST_METHOD']=== 'POST') {
    requireRole(['superadmin','admin','employee']);
    $branch=$_GET['branch']??'';
    $r=withBranchTransaction($branch, fn()=>updateDeliveryStatus($branch,$_GET['id']??'','delivered'));
    $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
    header("Location: index.php?page=invoices&branch=".($_GET['branch']??'')."&ym=".($_GET['ym']??date('Y_m'))); exit;
}
if ($page==='invoices' && ($_GET['action']??'')==='update' && $_SERVER['REQUEST_METHOD']==='POST') {
    requireRole(['superadmin','admin']);
    $branch=$_GET['branch']??'';
    $r=withBranchTransaction($branch, fn()=>updateInvoice($branch,$_GET['id']??'',$_POST));
    $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
    header("Location: index.php?page=invoices&branch=".($_GET['branch']??'')."&ym=".($_GET['ym']??date('Y_m'))); exit;
}
if ($page==='invoices' && ($_GET['action']??'')==='cancel' && $_SERVER['REQUEST_METHOD']==='POST') {
    requireRole(['superadmin','admin']);
    $branch=$_GET['branch']??'';
    $r=withBranchTransaction($branch, fn()=>cancelInvoice($branch,$_POST['invoice_id']??'',$_POST['cancel_reason']??''));
    $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
    header("Location: index.php?page=invoices&branch=".urlencode($_GET['branch']??'')."&ym=".urlencode($_GET['ym']??date('Y_m'))); exit;
}

// RECEIVABLES
if ($page==='receivables') {
    requireRole(['superadmin','admin','employee']);
    $action=$_GET['action']??'';
    $branch=$_GET['branch']??firstAccessibleBranchId();
    if ($action==='payment_save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=withBranchTransaction($branch, fn()=>receivableCreatePayment($branch,$_POST));
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header("Location: index.php?page=receivables&branch=".urlencode($branch)); exit;
    }
    if ($action==='payment_delete' && $_SERVER['REQUEST_METHOD']==='POST') {
        requireRole(['superadmin','admin']);
        $r=withBranchTransaction($branch, fn()=>receivableDeletePayment($branch,$_POST['payment_id']??'',$_POST['delete_reason']??''));
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header("Location: index.php?page=receivables&branch=".urlencode($branch)); exit;
    }
}

// CASHBOOK
if ($page==='cashbook') {
    requireRole(['superadmin','admin','employee']);
    $action=$_GET['action']??'';
    $branch=$_GET['branch']??firstAccessibleBranchId();
    if ($action==='save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=withBranchTransaction($branch, fn()=>cashbookSaveManual($branch,$_POST));
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        $ym = cashbookYearMonthFromDate($_POST['entry_date'] ?? date('Y-m-d'));
        header("Location: index.php?page=cashbook&branch=".urlencode($branch)."&ym=".urlencode($ym)); exit;
    }
    if ($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=withBranchTransaction($branch, fn()=>cashbookSoftDelete($branch,$_POST['id']??''));
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header("Location: index.php?page=cashbook&branch=".urlencode($branch)."&ym=".urlencode($_GET['ym']??date('Y_m'))); exit;
    }
}

if ($page==='integrity') {
    requireRole(['superadmin','admin']);
    $branch=$_GET['branch']??firstAccessibleBranchId();
    if (!canAccessBranch($branch)) {
        $_SESSION['flash']=['type'=>'danger','message'=>'Không có quyền truy cập chi nhánh này'];
        header('Location: index.php'); exit;
    }
    $integrityAction = $_GET['action'] ?? '';
    if ($integrityAction === 'check' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $r=withBranchTransaction($branch, fn()=>integrityRecordCheck($branch));
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        $runId = $r['run']['id'] ?? '';
        header('Location: index.php?page=integrity&branch='.urlencode($branch).'&tab=history'.($runId ? '&run='.urlencode($runId) : '')); exit;
    }
    if ($integrityAction==='repair' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=withBranchTransaction($branch, fn()=>integrityRepairLinks($branch));
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        $runId = $r['run_id'] ?? '';
        header('Location: index.php?page=integrity&branch='.urlencode($branch).'&tab=history'.($runId ? '&run='.urlencode($runId) : '')); exit;
    }
}

// INVENTORY
if ($page==='inventory') {
    requireRole(['superadmin','admin','employee']);
    $branch=$_GET['branch']??firstAccessibleBranchId();
    $action=$_GET['action']??'';
    if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=inventoryAdjustmentCreate($branch,$_POST);
    } elseif ($action==='approve' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=inventoryAdjustmentApprove($branch,$_POST['id']??'');
    } elseif ($action==='cancel' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=inventoryAdjustmentCancel($branch,$_POST['id']??'');
    } elseif ($action==='reverse' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=inventoryAdjustmentReverse($branch,$_POST['id']??'',$_POST['reason']??'');
    }
    if (isset($r)) {
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=inventory&branch='.urlencode($branch)); exit;
    }
}

// SALES RETURNS
if ($page==='returns') {
    requireRole(['superadmin','admin','employee']);
    $branch=$_GET['branch']??firstAccessibleBranchId();
    $action=$_GET['action']??'';
    if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=salesReturnCreate($branch,$_POST);
    } elseif ($action==='approve' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=salesReturnApprove($branch,$_POST['id']??'');
    } elseif ($action==='cancel' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=salesReturnCancel($branch,$_POST['id']??'');
    } elseif ($action==='reverse' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=salesReturnReverse($branch,$_POST['id']??'',$_POST['reason']??'');
    }
    if (isset($r)) {
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=returns&branch='.urlencode($branch)); exit;
    }
}

// CATEGORIES
if ($page==='categories') {
    requireRole(['superadmin','admin']);
    $action=$_GET['action']??''; $bc=$_GET['branch']??firstAccessibleBranchId();
    if ($action==='save' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $r=withBranchTransaction($bc, fn()=>saveCategory($bc,$_POST));
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=categories&branch={$bc}"); exit;
    }
    if ($action==='toggle' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $cat=getCategoryByKey($bc,$_GET['key']??'');
        if($cat){$cat['active']=!($cat['active']??true);$cat['original_key']=$cat['key'];withBranchTransaction($bc, fn()=>saveCategory($bc,$cat));}
        header("Location: index.php?page=categories&branch={$bc}"); exit;
    }
    if ($action==='delete' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $r=withBranchTransaction($bc, fn()=>deleteCategory($bc,$_GET['key']??''));
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=categories&branch={$bc}"); exit;
    }
}

// USERS
if ($page==='users') {
    requireRole(['superadmin','admin']);
    $action=$_GET['action']??'';
    if ($action==='branches_save' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','admin']);
        $r=saveBranchesSettings($_POST);
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header('Location: index.php?page=users'); exit;
    }
    if ($action==='save' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        if(($_POST['action_type']??'add')==='add' && ($_POST['password']??''  )!==($_POST['password_confirm']??'')){
            $_SESSION['flash']=['type'=>'danger','message'=>'Mật khẩu xác nhận không khớp']; header('Location: index.php?page=users'); exit;
        }
        $r=saveUser($_POST);
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header('Location: index.php?page=users'); exit;
    }
    if ($action==='reset_password' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $p=$_POST['new_password']??''; $p2=$_POST['confirm_password']??'';
        if($p!==$p2){$_SESSION['flash']=['type'=>'danger','message'=>'Mật khẩu không khớp'];header('Location: index.php?page=users');exit;}
        $r=resetPassword($_POST['username']??'',$p);
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header('Location: index.php?page=users'); exit;
    }
    if ($action==='toggle' && $_SERVER['REQUEST_METHOD']=== 'POST'){$r=toggleUserActive($_POST['username']??'');$_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];header('Location: index.php?page=users');exit;}
    if ($action==='delete' && $_SERVER['REQUEST_METHOD']=== 'POST'){$r=deleteUser($_POST['username']??'');$_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];header('Location: index.php?page=users');exit;}
}

// BACKUP
if ($page==='backup') {
    requireRole(['superadmin','admin']);
    $action=$_GET['action']??''; $bp=backupDir();
    if(!is_dir($bp))mkdir($bp,0755,true);
    if ($action==='create' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $r=backupCreateZip('manual');
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['success'] ? "Backup thành công: {$r['filename']} (".round(($r['size']??0)/1024,1)." KB)" : $r['message']];
        header('Location: index.php?page=backup'); exit;
    }
    if ($action==='download') {
        $file=basename($_GET['file']??''); $path=$bp.'/'.$file;
        if($file&&file_exists($path)&&str_starts_with($file,'backup_')){
            header('Content-Type: application/zip'); header("Content-Disposition: attachment; filename=\"{$file}\""); header('Content-Length: '.filesize($path)); readfile($path); exit;
        }
        header('Location: index.php?page=backup'); exit;
    }
    if ($action==='delete' && $_SERVER['REQUEST_METHOD']=== 'POST'){$f=basename($_POST['file']??'');$p=$bp.'/'.$f;if($f&&file_exists($p)&&str_starts_with($f,'backup_')){unlink($p);$_SESSION['flash']=['type'=>'success','message'=> "Đã xóa: {$f}"];}header('Location: index.php?page=backup');exit;}
    if ($action==='cleanup' && $_SERVER['REQUEST_METHOD']=== 'POST'){$files=glob($bp.'/backup_*.zip')??[];usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));$d=0;foreach(array_slice($files,10) as $f){unlink($f);$d++;}$_SESSION['flash']=['type'=>'success','message'=> "Đã xóa {$d} file cũ"];header('Location: index.php?page=backup');exit;}
    if ($action==='restore_existing' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $file=basename($_POST['file']??''); $path=$bp.'/'.$file;
        if(!$file||!file_exists($path)||!str_starts_with($file,'backup_')){
            $_SESSION['flash']=['type'=>'danger','message'=>'File backup không hợp lệ'];
        } else {
            $r=backupRestoreFromZip($path);
            $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        }
        header('Location: index.php?page=backup'); exit;
    }
    if ($action==='restore_upload' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $upload=$_FILES['restore_file']??null;
        if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
            $_SESSION['flash']=['type'=>'danger','message'=>'Vui lòng chọn file ZIP backup để phục hồi'];
            header('Location: index.php?page=backup'); exit;
        }
        if(strtolower(pathinfo($upload['name']??'',PATHINFO_EXTENSION))!=='zip'){
            $_SESSION['flash']=['type'=>'danger','message'=>'Chỉ hỗ trợ phục hồi từ file .zip'];
            header('Location: index.php?page=backup'); exit;
        }
        $tmp=$bp.'/restore_upload_'.date('Y-m-d_H-i-s').'_'.bin2hex(random_bytes(3)).'.zip';
        if(!move_uploaded_file($upload['tmp_name'],$tmp)){
            $_SESSION['flash']=['type'=>'danger','message'=>'Không lưu được file upload'];
            header('Location: index.php?page=backup'); exit;
        }
        $r=backupRestoreFromZip($tmp);
        @unlink($tmp);
        $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
        header('Location: index.php?page=backup'); exit;
    }
}

// RENDER
$viewMap=[
    'dashboard'           => BASE_PATH.'/views/dashboard/index.php',
    'superadmin_dashboard'=> BASE_PATH.'/views/superadmin/dashboard.php',
    'license'             => BASE_PATH.'/views/license/index.php',
    'products'            => BASE_PATH.'/views/products/index.php',
    'imports'             => BASE_PATH.'/views/imports/index.php',
    'invoice'             => BASE_PATH.'/views/invoices/create.php',
    'invoices'            => BASE_PATH.'/views/invoices/list.php',
    'receivables'         => BASE_PATH.'/views/receivables/index.php',
    'cashbook'            => BASE_PATH.'/views/cashbook/index.php',
    'reports'             => BASE_PATH.'/views/reports/index.php',
    'integrity'           => BASE_PATH.'/views/integrity/index.php',
    'inventory'           => BASE_PATH.'/views/inventory/index.php',
    'returns'             => BASE_PATH.'/views/returns/index.php',
    'users'               => BASE_PATH.'/views/users/index.php',
    'categories'          => BASE_PATH.'/views/categories/index.php',
    'help'                => BASE_PATH.'/views/help/index.php',
    'backup'              => BASE_PATH.'/views/backup/index.php',
    'edit_invoice'        => BASE_PATH.'/views/invoices/edit.php',
    'search_invoices'     => BASE_PATH.'/views/invoices/search.php',
    'profile'             => BASE_PATH.'/views/profile/index.php',
];

$viewFile=$viewMap[$page]??null;
if(!$viewFile||!file_exists($viewFile)){
    $_SESSION['flash']=['type'=>'danger','message'=> "Trang '{$page}' không tồn tại"]; header('Location: index.php'); exit;
}

$salesOnly=['invoice','invoices','search_invoices','edit_invoice','receivables','returns'];
$warehouseOnly=['imports'];
if(in_array($page,$salesOnly)) requireRole(['superadmin','admin','employee']);
if(in_array($page,$warehouseOnly)) requireRole(['superadmin','admin','employee']);
if($page === 'reports') requireRole(['superadmin','admin']);
if($reqBranch&&!canAccessBranch($reqBranch)){
    $_SESSION['flash']=['type'=>'danger','message'=> 'Không có quyền truy cập chi nhánh này']; header('Location: index.php'); exit;
}
include $viewFile;
