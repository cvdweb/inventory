<?php
// ============================================================
// HELPER: CÀI ĐẶT HỆ THỐNG / CỬA HÀNG (data/system_settings.json)
// Nơi đặt tên cửa hàng để khi triển khai cho khách chỉ cần đổi tên.
// ============================================================

define('SYSTEM_SETTINGS_FILE', DATA_PATH . '/system_settings.json');

/**
 * Giá trị mặc định của cài đặt hệ thống.
 * Lấy từ hằng số BUSINESS / APP_NAME (đã được nạp từ file cài đặt nếu có).
 */
function systemSettingsDefaults(): array
{
    return [
        'store_name' => BUSINESS['name'] ?? '',
        'app_name'   => APP_NAME,
        'address'    => BUSINESS['address'] ?? '',
        'phone'      => BUSINESS['phone'] ?? '',
        'email'      => BUSINESS['email'] ?? '',
        'tax_code'   => BUSINESS['tax_code'] ?? '',
        'slogan'     => BUSINESS['slogan'] ?? '',
        'theme'      => 'amber',
    ];
}

/**
 * Danh sách màu giao diện phù hợp ngành vật liệu xây dựng.
 */
function systemThemes(): array
{
    return [
        'amber'  => ['label' => 'Cam công trình', 'accent' => '#f59e0b', 'accent_dark' => '#d97706', 'sidebar' => '#111827'],
        'steel'  => ['label' => 'Xanh thép',      'accent' => '#2563eb', 'accent_dark' => '#1d4ed8', 'sidebar' => '#0f172a'],
        'cement' => ['label' => 'Xám bê tông',    'accent' => '#475569', 'accent_dark' => '#334155', 'sidebar' => '#1e293b'],
        'brick'  => ['label' => 'Đỏ gạch',        'accent' => '#dc2626', 'accent_dark' => '#b91c1c', 'sidebar' => '#1a1113'],
        'safety' => ['label' => 'Xanh an toàn',   'accent' => '#16a34a', 'accent_dark' => '#15803d', 'sidebar' => '#0f1d16'],
        'teal'   => ['label' => 'Xanh ngọc',      'accent' => '#0d9488', 'accent_dark' => '#0f766e', 'sidebar' => '#0f1d1c'],
    ];
}

function currentThemeKey(): string
{
    $key = (string)(systemSettingsGet()['theme'] ?? 'amber');
    return isset(systemThemes()[$key]) ? $key : 'amber';
}

function currentTheme(): array
{
    return systemThemes()[currentThemeKey()];
}

function themeHexToRgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/**
 * Sinh thẻ <style> ghi đè biến CSS theo màu giao diện đã chọn.
 */
function themeCssTag(): string
{
    $theme = currentTheme();
    [$r, $g, $b] = themeHexToRgb($theme['accent']);
    $rgba = fn(float $a) => "rgba({$r},{$g},{$b},{$a})";
    $decls = [
        '--accent:' . $theme['accent'],
        '--accent-dark:' . $theme['accent_dark'],
        '--sidebar-active:' . $theme['accent'],
        '--bg-sidebar:' . $theme['sidebar'],
        '--sidebar-active-bg:' . $rgba(.12),
        '--accent-soft:' . $rgba(.12),
        '--accent-softer:' . $rgba(.08),
        '--accent-ring:' . $rgba(.15),
        '--accent-glow:' . $rgba(.3),
        '--accent-glow-strong:' . $rgba(.4),
        '--accent-avatar:' . $rgba(.2),
    ];
    return '<style id="themeVars">:root{' . implode(';', $decls) . ';}</style>';
}

function themeColor(): string
{
    return currentTheme()['sidebar'];
}

function systemSettingsGet(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $saved = readJson(SYSTEM_SETTINGS_FILE);
    return $cache = array_merge(systemSettingsDefaults(), is_array($saved) ? $saved : []);
}

/**
 * Ghi đè một phần cài đặt (chỉ những khóa hợp lệ), giữ nguyên phần còn lại.
 */
function systemSettingsWrite(array $values): bool
{
    $allowed = systemSettingsDefaults();
    $values  = array_intersect_key($values, $allowed);
    $merged  = array_merge($allowed, readJson(SYSTEM_SETTINGS_FILE), $values);
    $merged['updated_at'] = date('Y-m-d H:i:s');
    $merged['updated_by'] = function_exists('currentUser') ? (currentUser()['username'] ?? 'system') : 'system';
    return writeJson(SYSTEM_SETTINGS_FILE, $merged);
}

/**
 * Lưu cài đặt từ form trang Cài Đặt.
 */
function systemSettingsSave(array $post): array
{
    $storeName = trim((string)($post['store_name'] ?? ''));
    if ($storeName === '') {
        return ['success' => false, 'message' => 'Vui lòng nhập tên cửa hàng / doanh nghiệp'];
    }
    $taxCode = trim((string)($post['tax_code'] ?? ''));
    if ($taxCode !== '' && !preg_match('/^\d{10}(-\d{3})?$/', $taxCode)) {
        return ['success' => false, 'message' => 'Mã số thuế phải gồm 10 chữ số hoặc dạng 10 chữ số-3 chữ số'];
    }

    $theme = (string)($post['theme'] ?? 'amber');
    if (!isset(systemThemes()[$theme])) $theme = 'amber';

    $values = [
        'store_name' => $storeName,
        'app_name'   => trim((string)($post['app_name'] ?? '')),
        'address'    => trim((string)($post['address'] ?? '')),
        'phone'      => trim((string)($post['phone'] ?? '')),
        'email'      => trim((string)($post['email'] ?? '')),
        'tax_code'   => $taxCode,
        'slogan'     => trim((string)($post['slogan'] ?? '')),
        'theme'      => $theme,
    ];

    if (!systemSettingsWrite($values)) {
        return ['success' => false, 'message' => 'Không lưu được cài đặt cửa hàng'];
    }
    systemSettingsSyncLicense($values);
    return ['success' => true, 'message' => 'Đã lưu cài đặt cửa hàng'];
}

/**
 * Đồng bộ thông tin doanh nghiệp sang hồ sơ giấy phép để nhất quán khi hiển thị.
 */
function systemSettingsSyncLicense(array $values): void
{
    if (!function_exists('licenseGet')) return;
    $license = licenseGet();
    if (!empty($values['store_name'])) $license['customer']['name'] = $values['store_name'];
    if (array_key_exists('address', $values)) $license['customer']['address'] = $values['address'];
    if (array_key_exists('phone', $values)) $license['customer']['phone'] = $values['phone'];
    if (array_key_exists('tax_code', $values)) $license['customer']['tax_code'] = $values['tax_code'];
    licenseSave($license);
}

/** Tên cửa hàng / doanh nghiệp hiển thị toàn hệ thống. */
function storeName(): string
{
    return trim((string)(systemSettingsGet()['store_name'] ?? '')) ?: (BUSINESS['name'] ?? APP_NAME);
}

/** Tên hệ thống hiển thị (tiêu đề trình duyệt, phụ đề). */
function systemName(): string
{
    return trim((string)(systemSettingsGet()['app_name'] ?? '')) ?: APP_NAME;
}
