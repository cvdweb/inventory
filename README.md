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

## 👤 Phân Quyền

| Vai trò | Phạm vi |
|---------|---------|
| `superadmin` | Quản trị hệ thống, giấy phép và hỗ trợ kỹ thuật |
| `admin` | Chủ cửa hàng, toàn bộ nghiệp vụ và tất cả chi nhánh |
| `employee` | Nghiệp vụ hằng ngày tại các chi nhánh được phân công |

Tài khoản được tạo và quản lý tại trang **Tài Khoản Nhân Viên**. Hệ thống không cung cấp mật khẩu mặc định cho bản production.

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

## 🔒 An Toàn Dữ Liệu JSON

- Đọc/ghi dùng khóa file để tránh xung đột đồng thời.
- Dữ liệu được ghi hoàn tất vào file tạm trước khi thay file thật.
- Trên Windows, hệ thống giữ bản trung gian để phục hồi nếu thao tác thay file thất bại.
- Các nghiệp vụ nhiều file như hóa đơn, tồn kho và sổ thu chi dùng khóa giao dịch cấp chi nhánh.
- Cấu trúc JSON hiện có được giữ nguyên; nâng cấp code không tự thay đổi chứng từ cũ.

---

## Chế Độ Sử Dụng

Superadmin cấu hình tại **Quản Lý Giấy Phép → Chế Độ Sử Dụng Của Khách Hàng**:

- `basic`: sản phẩm, nhập hàng, hóa đơn và trả hàng theo hóa đơn.
- `standard`: thêm nhập hàng loạt, công nợ, kiểm kê và báo cáo.
- `full`: thêm thu chi và kiểm tra toàn vẹn dữ liệu.

Cấu hình được lưu tại `data/feature_settings.json`. Nếu file chưa tồn tại, hệ thống mặc định dùng `full` để bảo đảm việc nâng cấp code không làm ẩn chức năng trên hệ thống đang vận hành. Đổi chế độ không xóa dữ liệu và các bút toán tự động vẫn tiếp tục được ghi nền.

Khi triển khai code mới lên hosting đang có dữ liệu, không ghi đè thư mục `data/`. Sau khi deploy, superadmin có thể chọn chế độ phù hợp trong giao diện.

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

Đăng nhập bằng `superadmin` hoặc `admin`, mở trang **Tài Khoản Nhân Viên** và tạo tài khoản mới. Mật khẩu được lưu bằng `password_hash()`; không chỉnh trực tiếp `data/users.json`.

---

*Phiên bản 1.0.0 — Hỗ trợ tiếng Việt Unicode*
