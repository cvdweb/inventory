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
        elseif(strlen($np)<6) $_SESSION['pwd_error']='Mật khẩu mới phải ít nhất 6 ký tự';
        elseif($np!==$cp) $_SESSION['pwd_error']='Mật khẩu xác nhận không khớp';
        elseif(verifyPassword($np, $cu['password'] ?? '')) $_SESSION['pwd_error']='Mật khẩu mới phải khác mật khẩu cũ';
        else{$r=resetPassword($un,$np);$_SESSION[$r['success']?'pwd_success':'pwd_error']=$r['success']?'Đổi mật khẩu thành công!':$r['message'];}
        header('Location: index.php?page=profile'); exit;
    }
}

// PRODUCTS
if ($page==='products') {
    $action=$_GET['action']??'';
    if ($action==='save' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','owner','admin']);
        $branch=$_GET['branch']??''; $cat=$_POST['category']??'';
        $r=productSave($branch,$cat,$_POST);
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=products&branch={$branch}".($cat?"&cat={$cat}":'')); exit;
    }
    if ($action==='delete' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        requireRole(['superadmin','owner','admin']);
        $branch=$_GET['branch']??''; $id=$_GET['id']??''; $cat=$_GET['cat']??'';
        $r=productDelete($branch,$cat,$id);
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=products&branch={$branch}".($cat?"&cat={$cat}":'')); exit;
    }
}

// IMPORTS
if ($page==='imports' && $_SERVER['REQUEST_METHOD']=== 'POST' && ($_POST['action']??'')=== 'import') {
    requireRole(['superadmin','owner','admin','warehouse']);
    $r=importProcess($_POST);
    $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['success']?"Nhập hàng thành công! Mã: {$r['id']}":$r['message']];
    header("Location: index.php?page=imports&branch=".urlencode($_POST['branch']??'')); exit;
}

// INVOICES
if ($page==='invoice' && $_SERVER['REQUEST_METHOD']=== 'POST' && ($_POST['action']??'')=== 'create_invoice') {
    requireRole(['superadmin','owner','admin','sales']);
    $r=invoiceProcess($_POST);
    if($r['success']){
        $_SESSION['flash']=['type'=>'success','message'=> "Xuất HĐ thành công! Mã: {$r['id']} — ".formatMoney($r['total'])];
        header("Location: index.php?page=invoices&branch=".urlencode($_POST['branch']??'')); 
    } else {
        $_SESSION['flash']=['type'=>'danger','message'=> $r['message']];
        header("Location: index.php?page=invoice&branch=".urlencode($_POST['branch']??'')); 
    }
    exit;
}
if ($page==='invoices' && ($_GET['action']??'')=== 'delivered' && $_SERVER['REQUEST_METHOD']=== 'POST') {
    requireRole(['superadmin','owner','admin','sales']);
    $r=updateDeliveryStatus($_GET['branch']??'',$_GET['id']??'','delivered');
    $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
    header("Location: index.php?page=invoices&branch=".($_GET['branch']??'')."&ym=".($_GET['ym']??date('Y_m'))); exit;
}
if ($page==='invoices' && ($_GET['action']??'')==='update' && $_SERVER['REQUEST_METHOD']==='POST') {
    requireRole(['superadmin','owner','admin']);
    $r=updateInvoice($_GET['branch']??'',$_GET['id']??'',$_POST);
    $_SESSION['flash']=['type'=>$r['success']?'success':'danger','message'=>$r['message']];
    header("Location: index.php?page=invoices&branch=".($_GET['branch']??'')."&ym=".($_GET['ym']??date('Y_m'))); exit;
}

// CATEGORIES
if ($page==='categories') {
    requireRole(['superadmin','owner','admin']);
    $action=$_GET['action']??''; $bc=$_GET['branch']??array_key_first(BRANCHES);
    if ($action==='save' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $r=saveCategory($bc,$_POST);
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=categories&branch={$bc}"); exit;
    }
    if ($action==='toggle' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $cat=getCategoryByKey($bc,$_GET['key']??'');
        if($cat){$cat['active']=!($cat['active']??true);$cat['original_key']=$cat['key'];saveCategory($bc,$cat);}
        header("Location: index.php?page=categories&branch={$bc}"); exit;
    }
    if ($action==='delete' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $r=deleteCategory($bc,$_GET['key']??'');
        $_SESSION['flash']=['type'=> $r['success']?'success':'danger','message'=> $r['message']];
        header("Location: index.php?page=categories&branch={$bc}"); exit;
    }
}

// USERS
if ($page==='users') {
    requireRole(['superadmin','owner','admin']);
    $action=$_GET['action']??'';
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
    requireRole(['superadmin','owner','admin']);
    $action=$_GET['action']??''; $bp=BASE_PATH.'/backups';
    if(!is_dir($bp))mkdir($bp,0755,true);
    if ($action==='create' && $_SERVER['REQUEST_METHOD']=== 'POST') {
        $fn="backup_manual_".date('Y-m-d_H-i-s').".zip"; $tf=$bp.'/'.$fn;
        $zip=new ZipArchive();
        if($zip->open($tf,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true){
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DATA_PATH,FilesystemIterator::SKIP_DOTS));
            foreach($it as $f){if($f->isFile())$zip->addFile($f->getRealPath(),substr($f->getRealPath(),strlen(BASE_PATH)+1));}
            $zip->close();
            $_SESSION['flash']=['type'=>'success','message'=> "Backup thành công: {$fn} (".round(filesize($tf)/1024,1)." KB)"];
        } else {$_SESSION['flash']=['type'=>'danger','message'=> 'Lỗi tạo backup'];}
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
}

// RENDER
$viewMap=[
    'dashboard'           => BASE_PATH.'/views/dashboard/index.php',
    'superadmin_dashboard'=> BASE_PATH.'/views/superadmin/dashboard.php',
    'products'            => BASE_PATH.'/views/products/index.php',
    'imports'             => BASE_PATH.'/views/imports/index.php',
    'invoice'             => BASE_PATH.'/views/invoices/create.php',
    'invoices'            => BASE_PATH.'/views/invoices/list.php',
    'reports'             => BASE_PATH.'/views/reports/index.php',
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

$salesOnly=['invoice','invoices','search_invoices','edit_invoice'];
$warehouseOnly=['imports'];
if(in_array($page,$salesOnly)) requireRole(['superadmin','owner','admin','sales']);
if(in_array($page,$warehouseOnly)) requireRole(['superadmin','owner','admin','warehouse']);
if($reqBranch&&!canAccessBranch($reqBranch)){
    $_SESSION['flash']=['type'=>'danger','message'=> 'Không có quyền truy cập chi nhánh này']; header('Location: index.php'); exit;
}
include $viewFile;
