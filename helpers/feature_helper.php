<?php

$configuredFeatureSettingsPath = trim((string)getenv('TRUONGPHU_FEATURE_SETTINGS_PATH'));
define('FEATURE_SETTINGS_FILE', $configuredFeatureSettingsPath !== '' ? $configuredFeatureSettingsPath : DATA_PATH . '/feature_settings.json');

function featureDefinitions(): array
{
    return [
        'bulk_import' => [
            'label' => 'Nhập dữ liệu hàng loạt',
            'description' => 'Nhập sản phẩm và hàng nhập kho bằng file CSV.',
            'icon' => 'bi-file-earmark-spreadsheet',
        ],
        'receivables' => [
            'label' => 'Bán hàng công nợ',
            'description' => 'Cho phép chọn Công nợ trên hóa đơn, thu nợ và in phiếu thu.',
            'icon' => 'bi-wallet2',
        ],
        'inventory' => [
            'label' => 'Kiểm kê kho',
            'description' => 'Kiểm kê thực tế, điều chỉnh tăng giảm và duyệt chênh lệch.',
            'icon' => 'bi-clipboard-check',
        ],
        'reports' => [
            'label' => 'Báo cáo nâng cao',
            'description' => 'Doanh thu, công nợ, kho, giao hàng và hiệu quả kinh doanh.',
            'icon' => 'bi-bar-chart',
        ],
        'cashbook' => [
            'label' => 'Quản lý thu chi',
            'description' => 'Sổ quỹ, phiếu thu chi thủ công và đối chiếu dòng tiền.',
            'icon' => 'bi-cash-stack',
        ],
        'integrity' => [
            'label' => 'Kiểm tra toàn vẹn dữ liệu',
            'description' => 'Phát hiện và sửa liên kết chứng từ không đồng bộ.',
            'icon' => 'bi-shield-check',
        ],
        'returns_menu' => [
            'label' => 'Không gian quản lý trả hàng',
            'description' => 'Hiện trang Trả hàng trong menu; gói Cơ bản vẫn trả được từ hóa đơn.',
            'icon' => 'bi-arrow-return-left',
        ],
    ];
}

function featureProfiles(): array
{
    return [
        'basic' => [
            'label' => 'Cơ bản',
            'description' => 'Nhập hàng, sản phẩm, bán hàng và trả hàng theo hóa đơn.',
            'features' => [],
        ],
        'standard' => [
            'label' => 'Tiêu chuẩn',
            'description' => 'Thêm nhập loạt, công nợ, kiểm kê và báo cáo.',
            'features' => ['bulk_import', 'receivables', 'inventory', 'reports', 'returns_menu'],
        ],
        'full' => [
            'label' => 'Đầy đủ',
            'description' => 'Toàn bộ nghiệp vụ và công cụ quản trị dữ liệu.',
            'features' => array_keys(featureDefinitions()),
        ],
    ];
}

function featureDefaultSettings(): array
{
    return [
        'profile' => 'full',
        'history' => [],
        'updated_at' => null,
        'updated_by' => null,
    ];
}

function featureGetSettings(): array
{
    if (isset($GLOBALS['truongphu_feature_settings_cache']) && is_array($GLOBALS['truongphu_feature_settings_cache'])) {
        return $GLOBALS['truongphu_feature_settings_cache'];
    }
    $saved = readJson(FEATURE_SETTINGS_FILE);
    $settings = array_merge(featureDefaultSettings(), is_array($saved) ? $saved : []);
    if (!isset(featureProfiles()[$settings['profile'] ?? ''])) $settings['profile'] = 'full';
    return $GLOBALS['truongphu_feature_settings_cache'] = $settings;
}

function featureResetCache(): void
{
    unset($GLOBALS['truongphu_feature_settings_cache']);
}

function currentFeatureProfile(): string
{
    return (string)(featureGetSettings()['profile'] ?? 'full');
}

function featureProfileInfo(string $profile = ''): array
{
    $profile = $profile ?: currentFeatureProfile();
    return featureProfiles()[$profile] ?? featureProfiles()['full'];
}

function featureProfileHas(string $profile, string $feature): bool
{
    $info = featureProfiles()[$profile] ?? featureProfiles()['full'];
    return in_array($feature, $info['features'] ?? [], true);
}

function featureEnabled(string $feature, ?string $profile = null): bool
{
    if ($profile === null && function_exists('isSuperAdmin') && isSuperAdmin()) return true;
    return featureProfileHas($profile ?: currentFeatureProfile(), $feature);
}

function featureRouteMap(): array
{
    return [
        'receivables' => 'receivables',
        'inventory' => 'inventory',
        'reports' => 'reports',
        'cashbook' => 'cashbook',
        'integrity' => 'integrity',
    ];
}

function featureRequire(string $feature): void
{
    if (featureEnabled($feature)) return;
    $definition = featureDefinitions()[$feature] ?? ['label' => 'Chức năng này'];
    $_SESSION['flash'] = [
        'type' => 'warning',
        'message' => ($definition['label'] ?? 'Chức năng này') . ' không có trong chế độ sử dụng hiện tại.',
    ];
    header('Location: index.php');
    exit;
}

function featureEnforcePage(string $page): void
{
    $feature = featureRouteMap()[$page] ?? '';
    if ($feature !== '') featureRequire($feature);
}

function featureProfileReadiness(string $targetProfile, ?array $branchIds = null): array
{
    if (!isset(featureProfiles()[$targetProfile])) {
        return ['success' => false, 'message' => 'Chế độ sử dụng không hợp lệ', 'blockers' => []];
    }

    $blockers = [];
    $branchIds = $branchIds ?? array_keys(getBranches());
    if (!featureProfileHas($targetProfile, 'receivables') && function_exists('getReceivableSummary')) {
        $totalBalance = 0.0;
        foreach ($branchIds as $branch) {
            $summary = getReceivableSummary($branch);
            $totalBalance += max(0, (float)($summary['total_balance'] ?? 0));
        }
        if ($totalBalance > 0.000001) {
            $blockers[] = 'Còn công nợ khách hàng ' . formatMoney($totalBalance) . '. Hãy thu hoặc xử lý hết trước khi ẩn Công nợ.';
        }
    }

    if (!featureProfileHas($targetProfile, 'inventory') && function_exists('getInventoryAdjustments')) {
        $drafts = 0;
        foreach ($branchIds as $branch) {
            $drafts += count(array_filter(getInventoryAdjustments($branch), fn($row) => ($row['status'] ?? '') === 'draft'));
        }
        if ($drafts > 0) $blockers[] = "Còn {$drafts} phiếu kiểm kê/điều chỉnh đang chờ duyệt.";
    }

    return [
        'success' => empty($blockers),
        'message' => empty($blockers) ? 'Có thể áp dụng chế độ này' : 'Chưa thể áp dụng chế độ đã chọn',
        'blockers' => $blockers,
    ];
}

function featureSaveProfile(string $profile): array
{
    if (!isset(featureProfiles()[$profile])) return ['success' => false, 'message' => 'Chế độ sử dụng không hợp lệ'];
    $current = featureGetSettings();
    if (($current['profile'] ?? 'full') === $profile) return ['success' => true, 'message' => 'Chế độ sử dụng không thay đổi'];

    $readiness = featureProfileReadiness($profile);
    if (!$readiness['success']) {
        return ['success' => false, 'message' => $readiness['message'] . ': ' . implode(' ', $readiness['blockers'])];
    }

    $user = function_exists('currentUser') ? currentUser() : [];
    $history = is_array($current['history'] ?? null) ? $current['history'] : [];
    $history[] = [
        'from' => $current['profile'] ?? 'full',
        'to' => $profile,
        'changed_by' => $user['name'] ?? 'System',
        'changed_by_username' => $user['username'] ?? '',
        'changed_at' => date('Y-m-d H:i:s'),
    ];
    $settings = [
        'profile' => $profile,
        'history' => array_slice($history, -30),
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $user['username'] ?? 'system',
    ];
    if (!writeJson(FEATURE_SETTINGS_FILE, $settings)) return ['success' => false, 'message' => 'Không lưu được cấu hình chức năng'];
    $GLOBALS['truongphu_feature_settings_cache'] = $settings;
    if (!featureProfileHas($profile, 'bulk_import')) {
        unset($_SESSION['product_bulk_preview'], $_SESSION['import_bulk_preview']);
    }
    return ['success' => true, 'message' => 'Đã chuyển sang chế độ ' . featureProfiles()[$profile]['label']];
}
