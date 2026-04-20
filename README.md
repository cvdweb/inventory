# Hệ Thống Quản Lý Nhập Xuất Hàng Hóa
## PHP Thuần + Bootstrap 5 + JSON Storage

---

## 📁 Cấu Trúc Thư Mục

```
inventory_app/
├── index.php                   ← Router chính
├── .htaccess                   ← Bảo mật Apache
├── config/
│   └── config.php              ← Cấu hình hệ thống, tài khoản, chi nhánh
├── controllers/
│   ├── auth_controller.php     ← Đăng nhập / phân quyền
│   ├── product_controller.php  ← CRUD sản phẩm
│   └── import_invoice_controller.php ← Nhập hàng & hóa đơn
├── helpers/
│   └── json_helper.php         ← Đọc/ghi JSON với flock()
├── models/                     ← (Mở rộng sau)
├── views/
│   ├── layouts/
│   │   ├── header.php          ← Sidebar + Topbar
│   │   └── footer.php          ← Scripts
│   ├── auth/
│   │   └── login.php           ← Trang đăng nhập
│   ├── dashboard/
│   │   └── index.php           ← Dashboard tổng quan
│   ├── products/
│   │   └── index.php           ← DS sản phẩm + thêm/sửa/xóa
│   ├── imports/
│   │   └── index.php           ← Phiếu nhập hàng
│   ├── invoices/
│   │   ├── create.php          ← Lập hóa đơn bán hàng
│   │   └── list.php            ← Danh sách hóa đơn
│   └── reports/
│       └── index.php           ← Báo cáo doanh thu, tồn kho
├── assets/
│   ├── css/style.css           ← Giao diện tùy chỉnh
│   └── js/app.js               ← JavaScript tương tác
└── data/
    ├── .htaccess               ← Chặn truy cập trực tiếp JSON
    ├── branch_1_vlxd/
    │   ├── products_ximang.json
    │   ├── products_sat_thep.json
    │   ├── products_gach_da.json
    │   ├── imports_YYYY_MM.json
    │   ├── invoices_YYYY_MM.json
    │   ├── customers.json
    │   └── suppliers.json
    └── branch_2_maiton/
        ├── products_ton_lanh.json
        ├── products_xa_go.json
        ├── products_phu_kien.json
        ├── imports_YYYY_MM.json
        ├── invoices_YYYY_MM.json
        ├── customers.json
        └── suppliers.json
```

---

## 👤 Tài Khoản Mặc Định

| Tài khoản     | Mật khẩu    | Vai trò              | Quyền                        |
|---------------|-------------|----------------------|------------------------------|
| `admin`       | `admin123`  | Quản trị viên        | Tất cả chức năng             |
| `nv_vlxd`     | `vlxd123`   | NV Bán Hàng VLXD     | Bán hàng chi nhánh VLXD      |
| `nv_maiton`   | `maiton123` | NV Bán Hàng Mái Tôn  | Bán hàng chi nhánh Mái Tôn   |
| `nv_nhaphang` | `nhap123`   | NV Nhập Hàng         | Nhập hàng tất cả chi nhánh   |

---

## 🚀 Cài Đặt trên Shared Hosting (cPanel)

### Bước 1: Upload files
- Upload toàn bộ thư mục `inventory_app/` lên `public_html/inventory_app/`
- Hoặc upload thẳng vào `public_html/` nếu muốn là website chính

### Bước 2: Phân quyền thư mục data
Qua File Manager hoặc SSH:
```bash
chmod 755 data/
chmod 755 data/branch_1_vlxd/
chmod 755 data/branch_2_maiton/
chmod 644 data/branch_1_vlxd/*.json
chmod 644 data/branch_2_maiton/*.json
```

### Bước 3: Cấu hình nếu cần
Mở `config/config.php` để thay đổi:
- Tên cửa hàng
- Múi giờ
- Thêm/bớt tài khoản người dùng
- Thêm/bớt nhóm hàng

### Bước 4: Truy cập
```
http://yourdomain.com/inventory_app/
```

---

## ⚙️ Yêu Cầu Hệ Thống

- PHP 8.0+
- Apache với mod_rewrite (thường có sẵn trên cPanel)
- Quyền ghi vào thư mục `data/`
- Extension: json (mặc định có)

---

## 🔒 Bảo Mật JSON với flock()

Tất cả thao tác đọc/ghi file đều dùng `flock()`:

```php
// Ghi file - khóa độc quyền
$fp = fopen($file, 'c+');
flock($fp, LOCK_EX);    // Chờ đến khi có thể ghi
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($data));
fflush($fp);
flock($fp, LOCK_UN);    // Giải phóng khóa
fclose($fp);

// Đọc file - khóa chia sẻ
flock($fp, LOCK_SH);    // Nhiều người đọc cùng lúc OK
$content = stream_get_contents($fp);
flock($fp, LOCK_UN);
```

---

## 📊 Công Thức Tính

| Thao tác  | Công thức                                  |
|-----------|--------------------------------------------|
| Nhập hàng | `stock = stock + qty`                      |
| Bán hàng  | `stock = stock - qty`                      |
| Thành tiền| `line_total = qty × price_out`             |
| Tổng đơn  | `invoice_total = Σ(line_total)`            |
| Cảnh báo  | Hiển thị đỏ khi `stock < min_stock`        |

---

## 🔄 Thêm Nhóm Hàng Mới

Trong `config/config.php`, thêm vào `PRODUCT_CATEGORIES`:
```php
'branch_1_vlxd' => [
    // ... các nhóm hiện có ...
    'son_nuoc' => ['name' => 'Sơn Nước', 'file' => 'products_son_nuoc.json'],
],
```
Hệ thống tự tạo file JSON khi có sản phẩm đầu tiên.

---

## 📝 Thêm Tài Khoản Người Dùng

Trong `config/config.php`, thêm vào `USERS`:
```php
'nv2_vlxd' => [
    'username' => 'nv2_vlxd',
    'password' => md5('matkhau'),
    'name'     => 'Nhân Viên 2 VLXD',
    'role'     => 'sales',      // admin | sales | warehouse
    'branch'   => 'branch_1_vlxd',
    'icon'     => 'bi-person-badge',
],
```

---

*Phiên bản 1.0.0 — Hỗ trợ tiếng Việt Unicode*
