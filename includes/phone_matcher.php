<?php

function phone_finder_ensure_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_sourcing_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        session_key VARCHAR(190) NULL,
        customer_name VARCHAR(190) NULL,
        customer_email VARCHAR(190) NULL,
        customer_phone VARCHAR(80) NULL,
        source VARCHAR(40) NOT NULL DEFAULT 'ai',
        existing_product_id BIGINT UNSIGNED NULL,
        requested_brand VARCHAR(120) NULL,
        requested_model VARCHAR(190) NOT NULL,
        requested_variant VARCHAR(190) NULL,
        preferences_json LONGTEXT NULL,
        ai_result_json LONGTEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'new',
        admin_notes TEXT NULL,
        converted_product_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_sourcing_status (status),
        KEY idx_sourcing_created (created_at),
        KEY idx_sourcing_user (user_id),
        KEY idx_sourcing_existing_product (existing_product_id),
        KEY idx_sourcing_converted_product (converted_product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (function_exists('store_table_has_column')) {
        if (!store_table_has_column($pdo, 'product_sourcing_requests', 'converted_product_id')) {
            $pdo->exec("ALTER TABLE product_sourcing_requests ADD COLUMN converted_product_id BIGINT UNSIGNED NULL AFTER admin_notes");
        }
    }

    $done = true;
}

function phone_finder_allowed_statuses(): array
{
    return [
        'new' => 'New',
        'reviewing' => 'Reviewing',
        'contacted_supplier' => 'Supplier contacted',
        'available' => 'Available',
        'converted_to_product' => 'Converted to product',
        'rejected' => 'Reject & delete',
    ];
}

function phone_finder_status_label(string $status): string
{
    $statuses = phone_finder_allowed_statuses();
    return $statuses[$status] ?? 'New';
}

function phone_finder_status_class(string $status): string
{
    return match ($status) {
        'new' => 'info',
        'reviewing', 'contacted_supplier' => 'warning',
        'available', 'converted_to_product' => 'good',
        'rejected' => 'danger',
        default => 'neutral',
    };
}

function phone_finder_text($value, int $max = 190): string
{
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    return mb_substr($value, 0, $max);
}

function phone_finder_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $value = strtolower(trim((string) $value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function phone_finder_normalize_preferences(array $input): array
{
    $budgetMin = max(0, (int) ($input['budget_min'] ?? 0));
    $budgetMax = max(0, (int) ($input['budget_max'] ?? 0));
    if ($budgetMax > 0 && $budgetMin > $budgetMax) {
        [$budgetMin, $budgetMax] = [$budgetMax, $budgetMin];
    }

    $os = strtolower(phone_finder_text($input['os'] ?? 'any', 20));
    if (!in_array($os, ['any', 'ios', 'android'], true)) {
        $os = 'any';
    }

    $brand = phone_finder_text($input['brand'] ?? 'any', 80);
    if (mb_strtolower($brand) === 'any') {
        $brand = 'any';
    }

    $useCase = strtolower(phone_finder_text($input['use_case'] ?? 'daily', 40));
    if (!in_array($useCase, ['daily', 'camera', 'gaming', 'battery', 'study', 'business'], true)) {
        $useCase = 'daily';
    }

    $importance = static function ($value): string {
        $value = strtolower(phone_finder_text($value, 20));
        return in_array($value, ['low', 'medium', 'high'], true) ? $value : 'medium';
    };

    $storageMin = (int) ($input['storage_min'] ?? 128);
    if (!in_array($storageMin, [64, 128, 256, 512, 1024], true)) {
        $storageMin = 128;
    }

    return [
        'budget_min' => $budgetMin,
        'budget_max' => $budgetMax,
        'os' => $os,
        'brand' => $brand,
        'use_case' => $useCase,
        'storage_min' => $storageMin,
        'battery_priority' => $importance($input['battery_priority'] ?? 'medium'),
        'camera_priority' => $importance($input['camera_priority'] ?? 'medium'),
        'performance_priority' => $importance($input['performance_priority'] ?? 'medium'),
        'need_5g' => phone_finder_bool($input['need_5g'] ?? false),
        'warranty_required' => phone_finder_bool($input['warranty_required'] ?? true),
        'notes' => phone_finder_text($input['notes'] ?? '', 800),
    ];
}

function phone_finder_specs_array($raw): array
{
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $specs = [];
    foreach ($decoded as $item) {
        if (is_array($item)) {
            $name = phone_finder_text($item['name'] ?? $item['label'] ?? '', 100);
            $value = phone_finder_text($item['value'] ?? '', 190);
            if ($name !== '' || $value !== '') {
                $specs[] = ['name' => $name, 'value' => $value];
            }
        }
    }
    return $specs;
}

function phone_finder_product_search_text(array $product): string
{
    $parts = [
        $product['name'] ?? '',
        $product['brand'] ?? '',
        $product['short_description'] ?? '',
        $product['description'] ?? '',
        $product['badge'] ?? '',
        $product['category_name'] ?? '',
    ];
    foreach (phone_finder_specs_array($product['specs_json'] ?? '') as $spec) {
        $parts[] = ($spec['name'] ?? '') . ' ' . ($spec['value'] ?? '');
    }
    return mb_strtolower(implode(' ', $parts));
}

function phone_finder_extract_storage_gb(string $text): int
{
    $max = 0;
    if (preg_match_all('/(\d{2,4})\s*(gb|g b|tb|t b)/iu', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $value = (int) $match[1];
            $unit = mb_strtolower(str_replace(' ', '', $match[2]));
            if ($unit === 'tb') {
                $value *= 1024;
            }
            if ($value >= 32 && $value <= 2048) {
                $max = max($max, $value);
            }
        }
    }
    return $max;
}

function phone_finder_extract_battery_mah(string $text): int
{
    $max = 0;
    if (preg_match_all('/(\d{4,5})\s*(mah|m ah)/iu', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $value = (int) $match[1];
            if ($value >= 2000 && $value <= 10000) {
                $max = max($max, $value);
            }
        }
    }
    return $max;
}

function phone_finder_product_os(array $product, string $text): string
{
    $brand = mb_strtolower((string) ($product['brand'] ?? ''));
    if (str_contains($brand, 'apple') || str_contains($text, 'iphone') || str_contains($text, 'ios')) {
        return 'ios';
    }
    return 'android';
}

function phone_finder_score_product(array $product, array $preferences): array
{
    $score = 0;
    $reasons = [];
    $text = phone_finder_product_search_text($product);
    $price = (float) ($product['price'] ?? 0);
    $budgetMin = (int) ($preferences['budget_min'] ?? 0);
    $budgetMax = (int) ($preferences['budget_max'] ?? 0);

    if ($budgetMax > 0) {
        if ($price <= $budgetMax && ($budgetMin <= 0 || $price >= $budgetMin)) {
            $score += 24;
            $reasons[] = 'Fits the selected budget range.';
        } elseif ($price <= $budgetMax * 1.08 && ($budgetMin <= 0 || $price >= $budgetMin * 0.90)) {
            $score += 12;
            $reasons[] = 'Very close to the selected budget.';
        } else {
            $score -= 12;
        }
    } else {
        $score += 8;
    }

    $productOs = phone_finder_product_os($product, $text);
    if (($preferences['os'] ?? 'any') === 'any') {
        $score += 5;
    } elseif (($preferences['os'] ?? 'any') === $productOs) {
        $score += 18;
        $reasons[] = $productOs === 'ios' ? 'Matches the iPhone/iOS preference.' : 'Matches the Android preference.';
    } else {
        $score -= 20;
    }

    $brand = mb_strtolower((string) ($product['brand'] ?? ''));
    $wantedBrand = mb_strtolower((string) ($preferences['brand'] ?? 'any'));
    if ($wantedBrand !== '' && $wantedBrand !== 'any') {
        if (str_contains($brand, $wantedBrand)) {
            $score += 16;
            $reasons[] = 'Matches the preferred brand.';
        } else {
            $score -= 8;
        }
    }

    $storage = phone_finder_extract_storage_gb($text);
    $storageMin = (int) ($preferences['storage_min'] ?? 128);
    if ($storage >= $storageMin) {
        $score += 13;
        $reasons[] = $storage . 'GB storage meets your minimum.';
    } elseif ($storage > 0) {
        $score -= 7;
    }

    $battery = phone_finder_extract_battery_mah($text);
    if (($preferences['battery_priority'] ?? 'medium') === 'high') {
        if ($battery >= 5000 || str_contains($text, 'all-day') || str_contains($text, 'long battery')) {
            $score += 15;
            $reasons[] = 'Strong battery profile for heavy daily use.';
        } else {
            $score -= 5;
        }
    } elseif ($battery >= 4500) {
        $score += 7;
    }

    if (($preferences['camera_priority'] ?? 'medium') === 'high' || ($preferences['use_case'] ?? '') === 'camera') {
        if (preg_match('/(pro camera|200mp|108mp|50mp|48mp|telephoto|leica|portrait|zoom)/iu', $text)) {
            $score += 16;
            $reasons[] = 'Camera specs match a photo-focused choice.';
        } else {
            $score -= 5;
        }
    }

    if (($preferences['performance_priority'] ?? 'medium') === 'high' || ($preferences['use_case'] ?? '') === 'gaming') {
        if (preg_match('/(a17|a16|snapdragon 8|dimensity 8|gen 3|gen 2|flagship|gaming|pro)/iu', $text)) {
            $score += 16;
            $reasons[] = 'Performance profile is suitable for demanding use.';
        } else {
            $score -= 5;
        }
    }

    if (($preferences['need_5g'] ?? false) === true) {
        if (str_contains($text, '5g')) {
            $score += 10;
            $reasons[] = 'Includes 5G connectivity.';
        } else {
            $score -= 10;
        }
    }

    if (($preferences['warranty_required'] ?? false) === true) {
        if (preg_match('/(warranty|garanti|official|distributor|service support|türkiye)/iu', $text)) {
            $score += 8;
            $reasons[] = 'Warranty/support wording is available in the listing.';
        }
    }

    $useCase = (string) ($preferences['use_case'] ?? 'daily');
    if ($useCase === 'study' || $useCase === 'business') {
        if (preg_match('/(productivity|s pen|usb-c|large storage|all-day|reliable|ecosystem)/iu', $text)) {
            $score += 8;
            $reasons[] = 'Good practical fit for study or work.';
        }
    } elseif ($useCase === 'daily') {
        if (preg_match('/(daily|reliable|balanced|popular|all-day|official)/iu', $text)) {
            $score += 8;
            $reasons[] = 'Balanced option for normal daily use.';
        }
    }

    $score = max(0, min(100, $score));
    if (!$reasons) {
        $reasons[] = 'Closest available match based on your choices.';
    }

    return ['score' => $score, 'reasons' => array_values(array_unique(array_slice($reasons, 0, 4)))];
}

function phone_finder_public_product_payload(array $product, array $scoreData, string $currency): array
{
    $slug = (string) ($product['slug'] ?? '');
    return [
        'id' => (int) ($product['id'] ?? 0),
        'name' => (string) ($product['name'] ?? ''),
        'brand' => (string) ($product['brand'] ?? ''),
        'slug' => $slug,
        'url' => site_url('product', ['slug' => $slug]),
        'image' => (string) ($product['image'] ?? ''),
        'short_description' => (string) ($product['short_description'] ?? ''),
        'price' => (float) ($product['price'] ?? 0),
        'price_formatted' => format_price((float) ($product['price'] ?? 0), $currency),
        'stock' => (int) ($product['stock'] ?? 0),
        'is_purchasable' => function_exists('public_product_is_purchasable') ? public_product_is_purchasable($product) : ((int) ($product['stock'] ?? 0) > 0),
        'score' => (int) ($scoreData['score'] ?? 0),
        'reasons' => $scoreData['reasons'] ?? [],
        'specs' => array_slice(phone_finder_specs_array($product['specs_json'] ?? ''), 0, 5),
    ];
}

function phone_finder_find_catalog_matches(PDO $pdo, array $preferences, string $currency, int $limit = 6): array
{
    store_ensure_public_product_columns($pdo);
    $visibleSql = store_public_product_visibility_sql('p');
    $stmt = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug
                         FROM products p
                         LEFT JOIN categories c ON c.id = p.category_id
                         WHERE {$visibleSql}
                           AND (p.product_type = 'smartphone' OR c.slug IN ('iphone','android') OR p.name LIKE '%iPhone%' OR p.name LIKE '%Galaxy%')
                         ORDER BY p.product_status = 'active' DESC, p.stock > 0 DESC, p.is_featured DESC, p.updated_at DESC, p.id DESC
                         LIMIT 120");
    $ranked = [];
    foreach ($stmt->fetchAll() as $product) {
        $score = phone_finder_score_product($product, $preferences);
        if ((int) $score['score'] < 45) {
            continue;
        }
        $ranked[] = phone_finder_public_product_payload($product, $score, $currency);
    }

    usort($ranked, static function (array $a, array $b): int {
        if (($b['score'] ?? 0) === ($a['score'] ?? 0)) {
            return ((int) ($b['stock'] ?? 0)) <=> ((int) ($a['stock'] ?? 0));
        }
        return ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0));
    });

    return array_slice($ranked, 0, $limit);
}

function phone_finder_should_use_ai(array $matches): bool
{
    if (!$matches) {
        return true;
    }
    $best = (int) ($matches[0]['score'] ?? 0);
    return $best < 70;
}

function phone_finder_session_key(): string
{
    if (function_exists('auth_guest_session_key')) {
        return auth_guest_session_key();
    }
    if (empty($_SESSION['_phone_finder_session'])) {
        $_SESSION['_phone_finder_session'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['_phone_finder_session'];
}


function phone_finder_public_relative_path(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    $relativePath = preg_replace('#/+#', '/', $relativePath) ?? $relativePath;
    return ltrim($relativePath, '/');
}

function phone_finder_request_images_public_base(): string
{
    // Keep request images under assets/uploads because this project already serves
    // that directory publicly. The previous root-level uploads/product_requests path
    // caused broken images on some hosts and inside admin AJAX pages.
    return 'assets/uploads/product_requests';
}

function phone_finder_request_images_dir(): string
{
    $root = function_exists('base_path') ? base_path() : dirname(__DIR__);
    $dir = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'product_requests';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function phone_finder_request_image_public_url(string $filename): string
{
    return phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . basename($filename));
}

function phone_finder_image_color_from_text(string $text): array
{
    $hash = crc32($text !== '' ? $text : 'phone');
    $hue = $hash % 360;
    return [
        'hsl(' . $hue . ',72%,48%)',
        'hsl(' . (($hue + 42) % 360) . ',72%,58%)',
        'hsl(' . (($hue + 210) % 360) . ',52%,28%)',
    ];
}

function phone_finder_svg_text_lines(string $text, int $maxLines = 3, int $lineLength = 20): array
{
    $text = phone_finder_text($text, 90);
    if ($text === '') {
        return ['Requested Phone'];
    }
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $try = trim($current . ' ' . $word);
        if ($current !== '' && mb_strlen($try) > $lineLength) {
            $lines[] = $current;
            $current = $word;
            if (count($lines) >= $maxLines - 1) {
                break;
            }
            continue;
        }
        $current = $try;
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    $lines = array_slice($lines, 0, $maxLines);
    return $lines ?: ['Requested Phone'];
}


function phone_finder_write_ai_preview_image(array $candidate): array
{
    $name = trim(implode(' ', array_filter([
        phone_finder_text($candidate['brand'] ?? '', 80),
        phone_finder_text($candidate['model'] ?? '', 120),
        phone_finder_text($candidate['variant'] ?? '', 80),
    ])));
    $name = $name !== '' ? $name : 'Phone suggestion';
    $hash = substr(hash('sha256', mb_strtolower($name)), 0, 18);
    $filename = 'ai-preview-' . $hash . '.svg';
    $dir = phone_finder_request_images_dir();
    $path = $dir . DIRECTORY_SEPARATOR . $filename;

    if (!is_file($path) || filesize($path) < 100) {
        [$c1, $c2, $c3] = phone_finder_image_color_from_text($name);
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $linesSvg = '';
        $y = 604;
        foreach (phone_finder_svg_text_lines($name, 3, 18) as $line) {
            $linesSvg .= '<text x="450" y="' . $y . '" text-anchor="middle" font-family="Arial, sans-serif" font-size="31" font-weight="800" fill="#0f172a">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</text>';
            $y += 42;
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="900" viewBox="0 0 900 900" role="img" aria-label="' . $safeName . '">'
            . '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient><radialGradient id="screen" cx="50%" cy="30%" r="80%"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#e2e8f0"/></radialGradient><filter id="shadow" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="30" stdDeviation="28" flood-color="#0f172a" flood-opacity=".30"/></filter></defs>'
            . '<rect width="900" height="900" rx="92" fill="url(#bg)"/>'
            . '<circle cx="170" cy="160" r="125" fill="#fff" opacity=".16"/><circle cx="765" cy="720" r="175" fill="#fff" opacity=".13"/><circle cx="736" cy="156" r="54" fill="#fff" opacity=".13"/>'
            . '<g filter="url(#shadow)"><rect x="286" y="92" width="328" height="700" rx="58" fill="#111827"/><rect x="309" y="130" width="282" height="608" rx="39" fill="url(#screen)"/><rect x="407" y="111" width="86" height="12" rx="6" fill="#334155"/><circle cx="450" cy="764" r="14" fill="#334155" opacity=".65"/></g>'
            . '<rect x="345" y="206" width="210" height="210" rx="42" fill="' . $c3 . '" opacity=".94"/><circle cx="406" cy="267" r="34" fill="#fff" opacity=".90"/><circle cx="494" cy="267" r="34" fill="#fff" opacity=".90"/><circle cx="406" cy="354" r="33" fill="#fff" opacity=".88"/><circle cx="495" cy="354" r="18" fill="#fff" opacity=".74"/>'
            . '<text x="450" y="542" text-anchor="middle" font-family="Arial, sans-serif" font-size="37" font-weight="800" fill="#0f172a">Phone request preview</text>'
            . $linesSvg
            . '<text x="450" y="735" text-anchor="middle" font-family="Arial, sans-serif" font-size="21" font-weight="700" fill="#475569">Preview image · our team confirms product details</text>'
            . '</svg>';
        file_put_contents($path, $svg);
    }

    return [
        'source' => 'local_ai_preview',
        'url' => phone_finder_request_image_public_url($filename),
        'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
        'original_url' => '',
        'label' => $name,
    ];
}

function phone_finder_write_request_placeholder_image(int $requestId, string $name): array
{
    $name = phone_finder_text($name !== '' ? $name : 'Requested Phone', 80);
    [$c1, $c2, $c3] = phone_finder_image_color_from_text($name);
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $linesSvg = '';
    $y = 598;
    foreach (phone_finder_svg_text_lines($name) as $line) {
        $linesSvg .= '<text x="450" y="' . $y . '" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="800" fill="#0f172a">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</text>';
        $y += 42;
    }
    $filename = 'request-' . $requestId . '.svg';
    $dir = phone_finder_request_images_dir();
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="900" viewBox="0 0 900 900" role="img" aria-label="' . $safeName . '">'
        . '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient><filter id="shadow" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="28" stdDeviation="28" flood-color="#0f172a" flood-opacity=".28"/></filter></defs>'
        . '<rect width="900" height="900" rx="92" fill="url(#bg)"/>'
        . '<circle cx="180" cy="150" r="120" fill="#fff" opacity=".16"/><circle cx="760" cy="720" r="170" fill="#fff" opacity=".12"/>'
        . '<g filter="url(#shadow)"><rect x="295" y="110" width="310" height="680" rx="54" fill="#111827"/><rect x="317" y="145" width="266" height="590" rx="34" fill="#f8fafc"/><rect x="406" y="126" width="88" height="12" rx="6" fill="#334155"/><circle cx="450" cy="760" r="15" fill="#334155" opacity=".65"/></g>'
        . '<rect x="346" y="205" width="208" height="208" rx="40" fill="' . $c3 . '" opacity=".92"/><circle cx="405" cy="265" r="34" fill="#fff" opacity=".88"/><circle cx="494" cy="265" r="34" fill="#fff" opacity=".88"/><circle cx="405" cy="354" r="34" fill="#fff" opacity=".88"/><circle cx="495" cy="354" r="18" fill="#fff" opacity=".74"/>'
        . '<text x="450" y="540" text-anchor="middle" font-family="Arial, sans-serif" font-size="38" font-weight="800" fill="#0f172a">Phone Request</text>'
        . $linesSvg
        . '</svg>';
    file_put_contents($path, $svg);
    return [
        'source' => 'placeholder',
        'url' => phone_finder_request_image_public_url($filename),
        'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
        'original_url' => '',
        'label' => $name,
    ];
}

function phone_finder_is_public_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function phone_finder_validate_remote_image_url($value): string
{
    $url = trim((string) $value);
    if ($url === '' || strlen($url) > 900 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
        return '';
    }
    $host = (string) ($parts['host'] ?? '');
    if ($host === '') {
        return '';
    }
    $ips = gethostbynamel($host) ?: [];
    if (!$ips) {
        return '';
    }
    foreach ($ips as $ip) {
        if (!phone_finder_is_public_ip($ip)) {
            return '';
        }
    }
    return $url;
}

function phone_finder_download_request_image(int $requestId, string $remoteUrl): ?array
{
    $remoteUrl = phone_finder_validate_remote_image_url($remoteUrl);
    if ($remoteUrl === '' || !function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($remoteUrl);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PhonixPhoneFinder/1.1; +https://phonix.local)',
        CURLOPT_REFERER => 'https://www.google.com/',
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/png,image/jpeg,*/*;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = strtolower(trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE)));
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status < 200 || $status >= 300 || strlen($body) > 2500000) {
        return null;
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $contentType = preg_replace('/;.*$/', '', $type) ?? $type;
    $ext = $map[$contentType] ?? '';
    if ($ext === '') {
        $info = @getimagesizefromstring($body);
        if (!is_array($info) || empty($info['mime']) || empty($map[strtolower((string) $info['mime'])])) {
            return null;
        }
        $ext = $map[strtolower((string) $info['mime'])];
    }

    if ($ext !== 'svg' && @getimagesizefromstring($body) === false) {
        return null;
    }

    $filename = 'request-' . $requestId . '.' . $ext;
    $path = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($path, $body);

    return [
        'source' => 'remote_import',
        'url' => phone_finder_request_image_public_url($filename),
        'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
        'original_url' => $remoteUrl,
        'label' => '',
    ];
}


function phone_finder_download_preview_image(string $remoteUrl, string $label): ?array
{
    $remoteUrl = phone_finder_validate_remote_image_url($remoteUrl);
    if ($remoteUrl === '' || !function_exists('curl_init')) {
        return null;
    }

    $hash = substr(hash('sha256', $remoteUrl . '|' . $label), 0, 18);
    foreach (['jpg', 'png', 'webp', 'gif'] as $knownExt) {
        $knownFile = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . 'preview-' . $hash . '.' . $knownExt;
        if (is_file($knownFile)) {
            return [
                'source' => 'preview_cache',
                'url' => phone_finder_request_image_public_url(basename($knownFile)),
                'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . basename($knownFile)),
                'original_url' => $remoteUrl,
                'label' => phone_finder_text($label, 100),
            ];
        }
    }

    $tmp = phone_finder_download_request_image(0, $remoteUrl);
    if ($tmp === null || empty($tmp['path'])) {
        return null;
    }

    $tmpPath = (string) ($tmp['path'] ?? '');
    $ext = pathinfo($tmpPath, PATHINFO_EXTENSION) ?: 'jpg';
    $sourceFull = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . basename($tmpPath);
    $filename = 'preview-' . $hash . '.' . $ext;
    $targetFull = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . $filename;

    if (is_file($sourceFull)) {
        @rename($sourceFull, $targetFull);
    }
    if (!is_file($targetFull)) {
        return null;
    }

    return [
        'source' => 'preview_cache',
        'url' => phone_finder_request_image_public_url($filename),
        'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
        'original_url' => $remoteUrl,
        'label' => phone_finder_text($label, 100),
    ];
}

function phone_finder_image_bad_result(string $title, string $context = '', string $url = ''): bool
{
    $text = mb_strtolower($title . ' ' . $context . ' ' . $url);
    return preg_match('/(case|cover|protector|tempered|glass|skin|wallpaper|mockup|template|repair|spare|battery replacement|charger|cable|magazine|news|review only|hands[- ]?on|youtube|pinterest|pngtree|shutterstock|alamy|istock|depositphotos)/iu', $text) === 1;
}

function phone_finder_image_result_score(array $candidate, string $url, string $title = '', string $context = ''): int
{
    if (phone_finder_image_bad_result($title, $context, $url)) {
        return -100;
    }

    $score = 0;
    $haystack = mb_strtolower($title . ' ' . $context . ' ' . $url);
    foreach (['brand', 'model', 'variant'] as $key) {
        $value = mb_strtolower(phone_finder_text($candidate[$key] ?? '', 120));
        if ($value !== '' && str_contains($haystack, $value)) {
            $score += $key === 'model' ? 35 : 15;
        }
    }

    $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
    foreach (['apple.com', 'samsung.com', 'mi.com', 'xiaomi', 'oppo.com', 'vivo.com', 'honor.com', 'huawei.com', 'motorola.com', 'nothing.tech', 'oneplus.com', 'gsmarena.com'] as $trusted) {
        if ($host !== '' && str_contains($host, $trusted)) {
            $score += 18;
            break;
        }
    }
    if (preg_match('/\.(jpg|jpeg|png|webp)(\?|$)/i', $url)) {
        $score += 8;
    }
    if (str_contains($haystack, 'official') || str_contains($haystack, 'product')) {
        $score += 8;
    }

    return $score;
}

function phone_finder_image_search_queries(array $candidate): array
{
    $brand = phone_finder_text($candidate['brand'] ?? '', 80);
    $model = phone_finder_text($candidate['model'] ?? '', 120);
    $variant = phone_finder_text($candidate['variant'] ?? '', 80);
    $custom = phone_finder_text($candidate['image_search_query'] ?? '', 180);
    $negative = '-case -cover -protector -tempered -wallpaper -mockup -repair';

    $queries = [];
    if ($custom !== '') {
        $queries[] = $custom . ' ' . $negative;
    }
    $base = trim($brand . ' ' . $model . ' ' . $variant);
    if ($base !== '') {
        $queries[] = $base . ' official product image smartphone ' . $negative;
        $queries[] = $base . ' product photo phone ' . $negative;
    }
    $short = trim($brand . ' ' . $model);
    if ($short !== '' && $short !== $base) {
        $queries[] = $short . ' official product image smartphone ' . $negative;
    }

    return array_values(array_unique(array_filter(array_map(static fn($q) => phone_finder_text($q, 220), $queries))));
}

function phone_finder_candidate_image_url(array $candidate): string
{
    if (isset($candidate['request_image']) && is_array($candidate['request_image']) && !empty($candidate['request_image']['original_url'])) {
        return phone_finder_validate_remote_image_url($candidate['request_image']['original_url']);
    }

    // Direct recommendation image URLs are disabled by default because they are the
    // main source of broken images. Enable only for testing with:
    // PHONIX_ALLOW_AI_IMAGE_URLS=1
    if (!phone_finder_bool(env_value('PHONIX_ALLOW_AI_IMAGE_URLS', '0'))) {
        return '';
    }

    foreach (['image_url', 'image', 'thumbnail', 'photo_url'] as $key) {
        if (!empty($candidate[$key]) && is_string($candidate[$key])) {
            $url = phone_finder_validate_remote_image_url($candidate[$key]);
            if ($url !== '') {
                return $url;
            }
        }
    }
    return '';
}

function phone_finder_local_image_is_readable(string $url): bool
{
    $url = phone_finder_public_relative_path($url);
    if ($url === '' || !preg_match('#^(assets/uploads/product_requests|assets/images)/#', $url)) {
        return false;
    }
    $root = function_exists('base_path') ? base_path() : dirname(__DIR__);
    $full = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $url);
    return is_file($full) && filesize($full) > 0;
}

function phone_finder_candidate_display_image_url(array $candidate): string
{
    if (isset($candidate['request_image']) && is_array($candidate['request_image']) && !empty($candidate['request_image']['url'])) {
        $url = phone_finder_public_relative_path((string) $candidate['request_image']['url']);
        if (str_starts_with($url, 'uploads/product_requests/')) {
            $url = phone_finder_request_images_public_base() . '/' . basename($url);
        }
        if (phone_finder_local_image_is_readable($url)) {
            return $url;
        }
    }

    foreach (['image_url', 'image', 'thumbnail', 'photo_url'] as $key) {
        $value = trim((string) ($candidate[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if (str_starts_with($value, 'uploads/product_requests/')) {
            $value = phone_finder_request_images_public_base() . '/' . basename($value);
        }
        if (preg_match('#^(assets/uploads/product_requests|assets/images)/#', $value) && phone_finder_local_image_is_readable($value)) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
    }

    return phone_finder_placeholder_public_url();
}


function phone_finder_image_provider(): string
{
    // Default to OpenAI web search when OpenAI is the active suggestion provider. This
    // finds real images/pages from the public web instead of generating images.
    $default = 'google_cse';
    if (function_exists('phone_finder_ai_provider') && phone_finder_ai_provider() === 'openai') {
        $default = 'openai_web';
    }
    $provider = strtolower(trim((string) env_value('PHONIX_IMAGE_PROVIDER', env_value('IMAGE_PROVIDER', $default))));
    return in_array($provider, ['openai_web', 'google_cse', 'serpapi', 'none'], true) ? $provider : $default;
}

function phone_finder_image_provider_configured(string $provider): bool
{
    if ($provider === 'none') {
        return false;
    }
    if ($provider === 'serpapi') {
        return trim((string) env_value('PHONIX_SERPAPI_KEY', env_value('SERPAPI_KEY', ''))) !== '';
    }
    if ($provider === 'google_cse') {
        $key = trim((string) env_value('PHONIX_GOOGLE_CSE_KEY', env_value('GOOGLE_CSE_API_KEY', env_value('GOOGLE_API_KEY', ''))));
        $cx = trim((string) env_value('PHONIX_GOOGLE_CSE_CX', env_value('GOOGLE_CSE_CX', '')));
        return $key !== '' && $cx !== '';
    }
    if ($provider === 'openai_web') {
        return phone_finder_openai_web_api_key() !== '' && function_exists('curl_init');
    }
    return false;
}

function phone_finder_image_search_configured(): bool
{
    if (phone_finder_image_provider_configured(phone_finder_image_provider())) {
        return true;
    }

    // If an old config points to google_cse/serpapi but keys are missing, still use
    // the OpenAI key already configured for the phone finder as a real web-image
    // fallback. This avoids blank cards with OpenAI-only setups.
    return phone_finder_image_provider() !== 'none' && phone_finder_image_provider_configured('openai_web');
}

function phone_finder_image_search_query(array $candidate): string
{
    $queries = phone_finder_image_search_queries($candidate);
    return $queries[0] ?? '';
}

function phone_finder_image_search_google_cse(string $query, array $candidate = []): ?array
{
    $key = trim((string) env_value('PHONIX_GOOGLE_CSE_KEY', env_value('GOOGLE_CSE_API_KEY', env_value('GOOGLE_API_KEY', ''))));
    $cx = trim((string) env_value('PHONIX_GOOGLE_CSE_CX', env_value('GOOGLE_CSE_CX', '')));
    if ($key === '' || $cx === '' || $query === '' || !function_exists('curl_init')) {
        return null;
    }

    $params = [
        'key' => $key,
        'cx' => $cx,
        'q' => $query,
        'searchType' => 'image',
        'num' => 8,
        'safe' => 'active',
        'imgType' => 'photo',
        'fileType' => 'jpg,png,webp',
        'gl' => 'tr',
        'lr' => 'lang_en',
    ];
    $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'PhonixPhoneFinder/1.0',
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
        error_log('[Phone Finder Image Search] Google CSE failed status=' . $status . ' query=' . $query);
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || empty($json['items']) || !is_array($json['items'])) {
        return null;
    }

    $best = null;
    $bestScore = -999;
    $probeCandidate = $candidate;
    foreach ($json['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $link = phone_finder_validate_remote_image_url($item['link'] ?? '');
        $title = phone_finder_text($item['title'] ?? '', 190);
        $context = phone_finder_text($item['image']['contextLink'] ?? '', 500);
        if ($link === '' || phone_finder_image_bad_result($title, $context, $link)) {
            continue;
        }
        $score = phone_finder_image_result_score($probeCandidate, $link, $title, $context);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = [
                'url' => $link,
                'source' => 'google_cse',
                'title' => $title,
                'context' => $context,
            ];
        }
    }

    return $best;
}

function phone_finder_image_search_serpapi(string $query, array $candidate = []): ?array
{
    $key = trim((string) env_value('PHONIX_SERPAPI_KEY', env_value('SERPAPI_KEY', '')));
    if ($key === '' || $query === '' || !function_exists('curl_init')) {
        return null;
    }

    $params = [
        'engine' => 'google_images',
        'q' => $query,
        'api_key' => $key,
        'ijn' => 0,
        'gl' => 'tr',
        'hl' => 'en',
        'safe' => 'active',
    ];
    $url = 'https://serpapi.com/search?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'PhonixPhoneFinder/1.0',
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
        error_log('[Phone Finder Image Search] SerpAPI failed status=' . $status . ' query=' . $query);
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || empty($json['images_results']) || !is_array($json['images_results'])) {
        return null;
    }

    $best = null;
    $bestScore = -999;
    $probeCandidate = $candidate;
    foreach ($json['images_results'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = phone_finder_text($item['title'] ?? '', 190);
        $context = phone_finder_text($item['source'] ?? ($item['link'] ?? ''), 500);
        foreach (['original', 'thumbnail'] as $imageKey) {
            $link = phone_finder_validate_remote_image_url($item[$imageKey] ?? '');
            if ($link === '' || phone_finder_image_bad_result($title, $context, $link)) {
                continue;
            }
            $score = phone_finder_image_result_score($probeCandidate, $link, $title, $context) + ($imageKey === 'original' ? 6 : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'url' => $link,
                    'source' => 'serpapi',
                    'title' => $title,
                    'context' => $context,
                ];
            }
        }
    }

    return $best;
}


function phone_finder_openai_web_api_key(): string
{
    if (function_exists('phone_finder_ai_provider') && phone_finder_ai_provider() === 'openai' && function_exists('phone_finder_ai_api_key')) {
        $key = phone_finder_ai_api_key();
        if ($key !== '') {
            return $key;
        }
    }
    $key = env_value('PHONIX_OPENAI_API_KEY', '');
    if ($key === '') {
        $key = env_value('OPENAI_API_KEY', '');
    }
    return trim((string) $key);
}

function phone_finder_openai_web_model(): string
{
    $model = trim((string) env_value('PHONIX_OPENAI_WEB_MODEL', ''));
    if ($model !== '') {
        return $model;
    }
    if (function_exists('phone_finder_ai_model')) {
        $model = trim((string) phone_finder_ai_model());
        if ($model !== '') {
            return $model;
        }
    }
    return 'gpt-4.1-mini';
}

function phone_finder_openai_web_endpoint(): string
{
    return trim((string) env_value('PHONIX_OPENAI_RESPONSES_URL', 'https://api.openai.com/v1/responses')) ?: 'https://api.openai.com/v1/responses';
}

function phone_finder_openai_web_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'direct_image_url' => [
                'type' => 'string',
                'description' => 'A direct HTTPS URL to a real product image file if found, otherwise empty string.',
            ],
            'product_page_url' => [
                'type' => 'string',
                'description' => 'A public HTTPS product page URL likely to contain og:image/twitter:image if no direct image URL is available.',
            ],
            'source_url' => [
                'type' => 'string',
                'description' => 'The source page URL used for the image search result.',
            ],
            'source_title' => ['type' => 'string'],
            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
        ],
        'required' => ['direct_image_url', 'product_page_url', 'source_url', 'source_title', 'confidence'],
    ];
}

function phone_finder_openai_web_extract_text($node): string
{
    if (is_string($node)) {
        return $node;
    }
    if (!is_array($node)) {
        return '';
    }
    if (($node['type'] ?? '') === 'output_text' && isset($node['text']) && is_string($node['text'])) {
        return $node['text'];
    }
    if (isset($node['output_text']) && is_string($node['output_text'])) {
        return $node['output_text'];
    }
    if (isset($node['text']) && is_string($node['text'])) {
        return $node['text'];
    }
    $parts = [];
    foreach ($node as $value) {
        $text = phone_finder_openai_web_extract_text($value);
        if ($text !== '') {
            $parts[] = $text;
        }
    }
    return implode("\n", $parts);
}

function phone_finder_openai_web_parse_json(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $m)) {
        $text = trim($m[1]);
    }
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $text = substr($text, $start, $end - $start + 1);
    }
    $text = preg_replace('/,\s*([}\]])/', '$1', $text) ?? $text;
    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

function phone_finder_openai_web_post(array $payload): array
{
    $apiKey = phone_finder_openai_web_api_key();
    if ($apiKey === '' || !function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'error' => 'OpenAI web search is not configured.', 'response' => null, 'raw' => ''];
    }

    $ch = curl_init(phone_finder_openai_web_endpoint());
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Could not initialize OpenAI web search.', 'response' => null, 'raw' => ''];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => (int) max(18, min(60, (int) env_value('PHONIX_OPENAI_WEB_TIMEOUT', '28'))),
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    return [
        'ok' => $status >= 200 && $status < 300 && is_array($json),
        'status' => $status,
        'error' => $error,
        'response' => is_array($json) ? $json : null,
        'raw' => is_string($raw) ? $raw : '',
    ];
}

function phone_finder_openai_web_image_payload(string $query, array $candidate, bool $withSchema): array
{
    $name = trim(implode(' ', array_filter([
        phone_finder_text($candidate['brand'] ?? '', 80),
        phone_finder_text($candidate['model'] ?? '', 120),
        phone_finder_text($candidate['variant'] ?? '', 80),
    ])));
    $name = $name !== '' ? $name : $query;

    $input = [
        [
            'role' => 'system',
            'content' => 'You find real smartphone product images from the public web. Do not generate images. Prefer official manufacturer pages, reputable retailers, or GSMArena-style product pages. Return only JSON.',
        ],
        [
            'role' => 'user',
            'content' => json_encode([
                'task' => 'Search the web for a real product image source for this smartphone.',
                'phone_name' => $name,
                'search_query' => $query,
                'rules' => [
                    'Never invent image URLs.',
                    'direct_image_url must be a direct https image file URL ending or responding as jpg, jpeg, png, webp, or gif. If unsure, leave it empty.',
                    'If no direct image file is found, return product_page_url for an official/reputable page that likely exposes og:image or twitter:image.',
                    'Avoid cases, screen protectors, wallpapers, accessories, repairs, hands-on articles, YouTube, Pinterest, and stock-photo sites.',
                ],
                'json_shape' => [
                    'direct_image_url' => '',
                    'product_page_url' => 'https://...',
                    'source_url' => 'https://...',
                    'source_title' => 'page title',
                    'confidence' => 'medium',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ],
    ];

    $payload = [
        'model' => phone_finder_openai_web_model(),
        'tools' => [[
            'type' => 'web_search',
            'user_location' => [
                'type' => 'approximate',
                'country' => 'TR',
                'city' => 'Istanbul',
                'timezone' => 'Europe/Istanbul',
            ],
        ]],
        // Images are mandatory here, so force the web-search tool instead of
        // allowing the model to answer from memory. The official OpenAI docs note
        // that tool_choice:auto can skip search.
        'tool_choice' => 'required',
        'include' => ['web_search_call.action.sources'],
        'input' => $input,
        'store' => false,
        'max_output_tokens' => 900,
    ];

    // Structured output plus hosted web search can fail on some accounts/models.
    // Keep this request plain JSON in the prompt, then parse the response and
    // sources defensively below.
    return $payload;
}

function phone_finder_html_attrs(string $tag): array
{
    $attrs = [];
    if (preg_match_all('/([a-zA-Z_:.-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/u', $tag, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $key = strtolower($m[1]);
            $value = $m[3] !== '' ? $m[3] : ($m[4] !== '' ? $m[4] : ($m[5] ?? ''));
            $attrs[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return $attrs;
}

function phone_finder_absolute_url(string $base, string $url): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '' || preg_match('/^(data|javascript):/i', $url)) {
        return '';
    }
    if (preg_match('#^https://#i', $url)) {
        return $url;
    }
    $baseParts = parse_url($base);
    if (!is_array($baseParts) || empty($baseParts['host'])) {
        return '';
    }
    $scheme = 'https';
    $host = $baseParts['host'];
    $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
    if (str_starts_with($url, '//')) {
        return $scheme . ':' . $url;
    }
    if (str_starts_with($url, '/')) {
        return $scheme . '://' . $host . $port . $url;
    }
    $path = (string) ($baseParts['path'] ?? '/');
    $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
    $combined = $dir . $url;
    $segments = [];
    foreach (explode('/', $combined) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }
    return $scheme . '://' . $host . $port . '/' . implode('/', $segments);
}

function phone_finder_fetch_public_html(string $url): string
{
    $url = phone_finder_validate_remote_image_url($url);
    if ($url === '' || !function_exists('curl_init')) {
        return '';
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return '';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PhonixPhoneFinder/1.2; +https://phonix.local)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.5'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = strtolower(trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE)));
    curl_close($ch);
    if (!is_string($body) || $body === '' || $status < 200 || $status >= 300 || strlen($body) > 2500000) {
        return '';
    }
    if ($type !== '' && !str_contains($type, 'text/html') && !str_contains($type, 'application/xhtml')) {
        return '';
    }
    return $body;
}

function phone_finder_collect_jsonld_images($value, array &$out): void
{
    if (is_string($value)) {
        $url = trim($value);
        if ($url !== '') {
            $out[] = $url;
        }
        return;
    }
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $child) {
        if (is_string($key) && strtolower($key) === 'image') {
            phone_finder_collect_jsonld_images($child, $out);
            continue;
        }
        if (is_array($child)) {
            phone_finder_collect_jsonld_images($child, $out);
        }
    }
}

function phone_finder_extract_product_image_from_page(string $pageUrl, array $candidate = []): ?array
{
    $pageUrl = phone_finder_validate_remote_image_url($pageUrl);
    if ($pageUrl === '') {
        return null;
    }
    $html = phone_finder_fetch_public_html($pageUrl);
    if ($html === '') {
        return null;
    }

    $candidates = [];
    if (preg_match_all('/<meta\b[^>]*>/iu', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            $attrs = phone_finder_html_attrs($tag);
            $name = strtolower((string) ($attrs['property'] ?? $attrs['name'] ?? ''));
            $content = trim((string) ($attrs['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if (in_array($name, ['og:image', 'og:image:url', 'og:image:secure_url', 'twitter:image', 'twitter:image:src'], true)) {
                $candidates[] = ['url' => $content, 'title' => $name, 'score' => 80];
            }
        }
    }

    if (preg_match_all('/<link\b[^>]*>/iu', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            $attrs = phone_finder_html_attrs($tag);
            $rel = strtolower((string) ($attrs['rel'] ?? ''));
            $href = trim((string) ($attrs['href'] ?? ''));
            if ($href !== '' && str_contains($rel, 'image_src')) {
                $candidates[] = ['url' => $href, 'title' => 'image_src', 'score' => 72];
            }
        }
    }

    if (preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $html, $scripts)) {
        foreach ($scripts[1] as $script) {
            $decoded = json_decode(html_entity_decode(trim($script), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (!is_array($decoded)) {
                continue;
            }
            $images = [];
            phone_finder_collect_jsonld_images($decoded, $images);
            foreach ($images as $img) {
                $candidates[] = ['url' => $img, 'title' => 'jsonld image', 'score' => 68];
            }
        }
    }

    if (preg_match_all('/<img\b[^>]*>/iu', $html, $tags)) {
        foreach (array_slice($tags[0], 0, 80) as $tag) {
            $attrs = phone_finder_html_attrs($tag);
            $src = trim((string) ($attrs['src'] ?? $attrs['data-src'] ?? $attrs['data-original'] ?? ''));
            if ($src === '' && !empty($attrs['srcset'])) {
                $srcsetParts = explode(',', (string) $attrs['srcset']);
                $src = trim(preg_replace('/\s+\d+[wx]$/', '', trim(end($srcsetParts))) ?? '');
            }
            if ($src === '') {
                continue;
            }
            $title = trim((string) ($attrs['alt'] ?? '') . ' ' . (string) ($attrs['title'] ?? ''));
            $score = phone_finder_image_result_score($candidate, $src, $title, $pageUrl) + 20;
            $candidates[] = ['url' => $src, 'title' => $title, 'score' => $score];
        }
    }

    $best = null;
    $bestScore = -999;
    foreach ($candidates as $item) {
        $absolute = phone_finder_absolute_url($pageUrl, (string) ($item['url'] ?? ''));
        $url = phone_finder_validate_remote_image_url($absolute);
        if ($url === '') {
            continue;
        }
        $title = phone_finder_text($item['title'] ?? '', 190);
        if (phone_finder_image_bad_result($title, $pageUrl, $url)) {
            continue;
        }
        $score = (int) ($item['score'] ?? 0) + phone_finder_image_result_score($candidate, $url, $title, $pageUrl);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = [
                'url' => $url,
                'source' => 'openai_web_page_meta',
                'title' => $title,
                'context' => $pageUrl,
            ];
        }
    }

    return $best;
}

function phone_finder_openai_web_urls_from_response(array $response): array
{
    $urls = [];
    $walk = static function ($node) use (&$walk, &$urls): void {
        if (is_array($node)) {
            $type = (string) ($node['type'] ?? '');
            if ($type === 'url_citation') {
                $url = (string) ($node['url'] ?? ($node['url_citation']['url'] ?? ''));
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
            // include=[web_search_call.action.sources] returns source objects here.
            foreach (['url', 'link', 'source_url'] as $key) {
                if (!empty($node[$key]) && is_string($node[$key])) {
                    $urls[] = $node[$key];
                }
            }
            foreach ($node as $value) {
                $walk($value);
            }
            return;
        }
        if (is_string($node) && preg_match_all('#https://[^\s\"\'<>\)]+#i', $node, $matches)) {
            foreach ($matches[0] as $url) {
                $urls[] = rtrim($url, '.,;');
            }
        }
    };
    $walk($response);
    return array_values(array_unique(array_filter($urls)));
}

function phone_finder_image_search_openai_web(string $query, array $candidate = []): ?array
{
    if (!phone_finder_image_provider_configured('openai_web')) {
        return null;
    }

    $attempts = [
        phone_finder_openai_web_image_payload($query, $candidate, false),
    ];

    $decoded = null;
    $response = null;
    foreach ($attempts as $payload) {
        $result = phone_finder_openai_web_post($payload);
        if (!$result['ok']) {
            $message = '';
            if (is_array($result['response'] ?? null)) {
                $message = (string) ($result['response']['error']['message'] ?? '');
            }
            error_log('[Phone Finder OpenAI Web Image] HTTP ' . $result['status'] . ': ' . phone_finder_text($message ?: $result['error'], 300));
            continue;
        }
        $response = is_array($result['response'] ?? null) ? $result['response'] : null;
        $text = phone_finder_openai_web_extract_text($response);
        $decoded = phone_finder_openai_web_parse_json($text);
        if (is_array($decoded)) {
            break;
        }
        error_log('[Phone Finder OpenAI Web Image] Could not parse search response: ' . mb_substr($text, 0, 800));
    }

    $pageUrls = [];
    if (is_array($decoded)) {
        $direct = phone_finder_validate_remote_image_url($decoded['direct_image_url'] ?? '');
        if ($direct !== '' && !phone_finder_image_bad_result((string) ($decoded['source_title'] ?? ''), (string) ($decoded['source_url'] ?? ''), $direct)) {
            return [
                'url' => $direct,
                'source' => 'openai_web_direct',
                'title' => phone_finder_text($decoded['source_title'] ?? '', 190),
                'context' => phone_finder_text($decoded['source_url'] ?? '', 500),
            ];
        }

        foreach (['product_page_url', 'source_url'] as $key) {
            $url = phone_finder_validate_remote_image_url($decoded[$key] ?? '');
            if ($url !== '') {
                $pageUrls[] = $url;
            }
        }
    }

    if (is_array($response)) {
        foreach (phone_finder_openai_web_urls_from_response($response) as $url) {
            $safe = phone_finder_validate_remote_image_url($url);
            if ($safe !== '') {
                $pageUrls[] = $safe;
            }
        }
    }

    foreach (array_values(array_unique($pageUrls)) as $pageUrl) {
        $image = phone_finder_extract_product_image_from_page($pageUrl, $candidate);
        if (is_array($image) && !empty($image['url'])) {
            return $image;
        }
    }

    return null;
}

function phone_finder_search_product_image(array $candidate): ?array
{
    // First try free public web sources that usually expose real phone product
    // photos without any paid image-generation usage. These do not need API keys.
    foreach ([phone_finder_image_search_gsmarena($candidate), phone_finder_image_search_wikimedia($candidate)] as $freeResult) {
        if (is_array($freeResult) && !empty($freeResult['url'])) {
            return $freeResult;
        }
    }

    if (!phone_finder_image_search_configured()) {
        return null;
    }

    $provider = phone_finder_image_provider();
    if (!phone_finder_image_provider_configured($provider) && phone_finder_image_provider_configured('openai_web')) {
        $provider = 'openai_web';
    }

    foreach (phone_finder_image_search_queries($candidate) as $query) {
        if ($query === '') {
            continue;
        }
        if ($provider === 'serpapi') {
            $result = phone_finder_image_search_serpapi($query, $candidate);
        } elseif ($provider === 'google_cse') {
            $result = phone_finder_image_search_google_cse($query, $candidate);
        } else {
            $result = phone_finder_image_search_openai_web($query, $candidate);
        }
        if (is_array($result) && !empty($result['url'])) {
            $result['query'] = $query;
            return $result;
        }
    }

    return null;
}


function phone_finder_free_web_image_search_enabled(): bool
{
    return phone_finder_bool(env_value('PHONIX_FREE_WEB_IMAGE_SEARCH', '1')) && function_exists('curl_init');
}

function phone_finder_candidate_full_name(array $candidate): string
{
    return trim(implode(' ', array_filter([
        phone_finder_text($candidate['brand'] ?? '', 80),
        phone_finder_text($candidate['model'] ?? '', 120),
        phone_finder_text($candidate['variant'] ?? '', 80),
    ])));
}

function phone_finder_fetch_json_url(string $url, int $timeout = 10): ?array
{
    if ($url === '' || !function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PhonixPhoneFinder/1.3)',
        CURLOPT_HTTPHEADER => ['Accept: application/json,text/javascript,*/*;q=0.5'],
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300 || strlen($raw) > 1200000) {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

function phone_finder_image_search_gsmarena(array $candidate): ?array
{
    if (!phone_finder_free_web_image_search_enabled()) {
        return null;
    }
    $name = phone_finder_candidate_full_name($candidate);
    if ($name === '') {
        return null;
    }

    $urls = [
        'https://www.gsmarena.com/results.php3?sQuickSearch=yes&sName=' . rawurlencode($name),
        'https://www.gsmarena.com/res.php3?sSearch=' . rawurlencode($name),
    ];

    $best = null;
    $bestScore = -999;
    foreach ($urls as $url) {
        $html = phone_finder_fetch_public_html($url);
        if ($html === '') {
            continue;
        }
        if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>\s*<img\b([^>]*)>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $attrs = phone_finder_html_attrs('<img ' . $m[2] . '>');
                $src = trim((string) ($attrs['src'] ?? $attrs['data-src'] ?? ''));
                if ($src === '') {
                    continue;
                }
                $title = trim(strip_tags(html_entity_decode((string) ($m[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $img = phone_finder_validate_remote_image_url(phone_finder_absolute_url($url, $src));
                if ($img === '' || phone_finder_image_bad_result($title, 'gsmarena.com', $img)) {
                    continue;
                }
                $score = phone_finder_image_result_score($candidate, $img, $title, 'gsmarena.com') + 30;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'url' => $img,
                        'source' => 'gsmarena_web',
                        'title' => phone_finder_text($title, 190),
                        'context' => phone_finder_absolute_url($url, (string) ($m[1] ?? '')),
                    ];
                }
            }
        }
    }

    return $best;
}

function phone_finder_image_search_wikimedia(array $candidate): ?array
{
    if (!phone_finder_free_web_image_search_enabled()) {
        return null;
    }
    $name = phone_finder_candidate_full_name($candidate);
    if ($name === '') {
        return null;
    }

    $params = [
        'action' => 'query',
        'generator' => 'search',
        'gsrsearch' => $name . ' smartphone',
        'gsrlimit' => 5,
        'prop' => 'pageimages|info',
        'piprop' => 'original|thumbnail',
        'pithumbsize' => 900,
        'inprop' => 'url',
        'format' => 'json',
        'origin' => '*',
    ];
    $url = 'https://en.wikipedia.org/w/api.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $json = phone_finder_fetch_json_url($url, 10);
    if (!is_array($json) || empty($json['query']['pages']) || !is_array($json['query']['pages'])) {
        return null;
    }

    $best = null;
    $bestScore = -999;
    foreach ($json['query']['pages'] as $page) {
        if (!is_array($page)) {
            continue;
        }
        $title = phone_finder_text($page['title'] ?? '', 190);
        $context = phone_finder_text($page['fullurl'] ?? 'wikipedia.org', 500);
        foreach (['original', 'thumbnail'] as $key) {
            $src = $page[$key]['source'] ?? '';
            $img = phone_finder_validate_remote_image_url($src);
            if ($img === '' || phone_finder_image_bad_result($title, $context, $img)) {
                continue;
            }
            $score = phone_finder_image_result_score($candidate, $img, $title, $context) + ($key === 'original' ? 18 : 10);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'url' => $img,
                    'source' => 'wikimedia_web',
                    'title' => $title,
                    'context' => $context,
                ];
            }
        }
    }

    return $best;
}

function phone_finder_placeholder_public_url(): string
{
    return 'assets/images/phone-ai-placeholder.svg';
}


function phone_finder_openai_image_generation_enabled(): bool
{
    if (!function_exists('phone_finder_ai_provider') || phone_finder_ai_provider() !== 'openai') {
        return false;
    }
    if (!phone_finder_bool(env_value('PHONIX_OPENAI_GENERATE_AI_IMAGES', '0'))) {
        return false;
    }
    return phone_finder_openai_image_api_key() !== '' && function_exists('curl_init');
}

function phone_finder_openai_image_api_key(): string
{
    if (function_exists('phone_finder_ai_api_key')) {
        $key = phone_finder_ai_api_key();
        if ($key !== '') {
            return $key;
        }
    }
    $key = env_value('PHONIX_OPENAI_API_KEY', '');
    if ($key === '') {
        $key = env_value('OPENAI_API_KEY', '');
    }
    return trim((string) $key);
}

function phone_finder_openai_image_model(): string
{
    return trim((string) env_value('PHONIX_OPENAI_IMAGE_MODEL', 'gpt-image-1-mini')) ?: 'gpt-image-1-mini';
}

function phone_finder_openai_image_endpoint(): string
{
    return trim((string) env_value('PHONIX_OPENAI_IMAGES_URL', 'https://api.openai.com/v1/images/generations')) ?: 'https://api.openai.com/v1/images/generations';
}

function phone_finder_openai_image_size(): string
{
    $size = trim((string) env_value('PHONIX_OPENAI_IMAGE_SIZE', '1024x1024'));
    return preg_match('/^\d{3,4}x\d{3,4}$/', $size) ? $size : '1024x1024';
}

function phone_finder_openai_image_prompt(array $candidate): string
{
    $name = trim(implode(' ', array_filter([
        phone_finder_text($candidate['brand'] ?? '', 80),
        phone_finder_text($candidate['model'] ?? '', 120),
        phone_finder_text($candidate['variant'] ?? '', 80),
    ])));
    $name = $name !== '' ? $name : 'recommended smartphone';
    $specs = array_values(array_filter(array_map(static fn($v): string => phone_finder_text($v, 80), (array) ($candidate['key_specs'] ?? []))));
    $specLine = $specs ? 'Key specs: ' . implode(', ', array_slice($specs, 0, 4)) . '.' : '';

    return 'Create a clean ecommerce product-card image for a smartphone recommendation named "' . $name . '". '
        . 'Show a modern smartphone front and back on a simple light studio background. '
        . 'Use a realistic product-render style, high clarity, no watermark, no text, no UI screenshots, no accessories, no hands. '
        . 'Avoid showing fake official logos or fake labels. This is a request preview, not an official manufacturer photo. '
        . $specLine;
}

function phone_finder_openai_image_cache_key(array $candidate): string
{
    $name = trim(implode(' ', array_filter([
        phone_finder_text($candidate['brand'] ?? '', 80),
        phone_finder_text($candidate['model'] ?? '', 120),
        phone_finder_text($candidate['variant'] ?? '', 80),
    ])));
    $specs = implode('|', array_map(static fn($v): string => phone_finder_text($v, 80), (array) ($candidate['key_specs'] ?? [])));
    return substr(hash('sha256', 'openai-image-v3|' . phone_finder_openai_image_model() . '|' . $name . '|' . $specs), 0, 22);
}

function phone_finder_save_openai_image_blob(string $blob, string $hash, string $label, string $original = ''): ?array
{
    if ($blob === '' || strlen($blob) > 5000000) {
        return null;
    }

    $info = @getimagesizefromstring($blob);
    if (!is_array($info) || empty($info['mime'])) {
        return null;
    }

    $mime = strtolower((string) $info['mime']);
    $ext = match ($mime) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '') {
        return null;
    }

    $filename = 'openai-preview-' . $hash . '.' . $ext;
    $path = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || filesize($path) < 100) {
        file_put_contents($path, $blob);
    }
    if (!is_file($path) || filesize($path) < 100) {
        return null;
    }

    return [
        'source' => 'openai_image',
        'url' => phone_finder_request_image_public_url($filename),
        'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
        'original_url' => $original,
        'label' => phone_finder_text($label, 100),
    ];
}

function phone_finder_generate_openai_preview_image(array $candidate): ?array
{
    if (!phone_finder_openai_image_generation_enabled()) {
        return null;
    }

    $label = trim(implode(' ', array_filter([
        phone_finder_text($candidate['brand'] ?? '', 80),
        phone_finder_text($candidate['model'] ?? '', 120),
        phone_finder_text($candidate['variant'] ?? '', 80),
    ])));
    $label = $label !== '' ? $label : 'Phone suggestion';
    $hash = phone_finder_openai_image_cache_key($candidate);

    foreach (['png', 'jpg', 'webp'] as $ext) {
        $cached = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . 'openai-preview-' . $hash . '.' . $ext;
        if (is_file($cached) && filesize($cached) > 100) {
            $filename = basename($cached);
            return [
                'source' => 'openai_image_cache',
                'url' => phone_finder_request_image_public_url($filename),
                'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
                'original_url' => '',
                'label' => phone_finder_text($label, 100),
            ];
        }
    }

    $payload = [
        'model' => phone_finder_openai_image_model(),
        'prompt' => phone_finder_openai_image_prompt($candidate),
        'n' => 1,
        'size' => phone_finder_openai_image_size(),
    ];

    $quality = trim((string) env_value('PHONIX_OPENAI_IMAGE_QUALITY', ''));
    if ($quality !== '') {
        $payload['quality'] = $quality;
    }

    $ch = curl_init(phone_finder_openai_image_endpoint());
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . phone_finder_openai_image_api_key(),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => (int) max(20, min(120, (int) env_value('PHONIX_OPENAI_IMAGE_TIMEOUT', '75'))),
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
        $message = $curlError;
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($json)) {
            $message = (string) ($json['error']['message'] ?? $message);
        }
        error_log('[Phone Finder OpenAI Image] HTTP ' . $status . ': ' . phone_finder_text($message, 300));
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        error_log('[Phone Finder OpenAI Image] Unreadable image response.');
        return null;
    }

    $first = is_array($json['data'][0] ?? null) ? $json['data'][0] : [];
    $b64 = (string) ($first['b64_json'] ?? '');
    if ($b64 !== '') {
        $blob = base64_decode($b64, true);
        if (is_string($blob) && $blob !== '') {
            return phone_finder_save_openai_image_blob($blob, $hash, $label, 'openai:b64_json');
        }
    }

    $url = phone_finder_validate_remote_image_url($first['url'] ?? '');
    if ($url !== '') {
        $cached = phone_finder_download_preview_image($url, $label);
        if (is_array($cached)) {
            $cached['source'] = 'openai_image_url_cache';
            return $cached;
        }
    }

    error_log('[Phone Finder OpenAI Image] No usable image data returned.');
    return null;
}

function phone_finder_copy_local_image_meta_to_request(int $requestId, array $imageMeta, string $name): ?array
{
    $existingPath = phone_finder_public_relative_path((string) ($imageMeta['path'] ?? ''));
    if ($existingPath === '' || !str_starts_with($existingPath, phone_finder_request_images_public_base() . '/')) {
        return null;
    }

    $root = function_exists('base_path') ? base_path() : dirname(__DIR__);
    $sourceFull = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $existingPath);
    if (!is_file($sourceFull) || filesize($sourceFull) < 100) {
        return null;
    }

    $ext = strtolower(pathinfo($sourceFull, PATHINFO_EXTENSION) ?: 'png');
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
        $ext = 'png';
    }
    $filename = 'request-' . $requestId . '.' . $ext;
    $targetFull = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . $filename;
    @copy($sourceFull, $targetFull);
    if (!is_file($targetFull) || filesize($targetFull) < 100) {
        return null;
    }

    return [
        'source' => (string) ($imageMeta['source'] ?? 'local_copy'),
        'url' => phone_finder_request_image_public_url($filename),
        'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
        'original_url' => (string) ($imageMeta['original_url'] ?? ''),
        'label' => phone_finder_text($name, 100),
    ];
}

function phone_finder_enrich_ai_recommendations_with_images(array $ai): array
{
    if (empty($ai['recommendations']) || !is_array($ai['recommendations'])) {
        return $ai;
    }

    $fallback = phone_finder_placeholder_public_url();

    foreach ($ai['recommendations'] as $index => $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        // Always create a neutral local placeholder first. This prevents a completely blank card
        // when the image-search provider is not configured or a remote image blocks download.
        $localPreview = phone_finder_write_ai_preview_image($candidate);
        $ai['recommendations'][$index]['request_image'] = $localPreview;
        $ai['recommendations'][$index]['image_fallback_url'] = $localPreview['url'];
        $ai['recommendations'][$index]['image_url'] = $localPreview['url'];
        $ai['recommendations'][$index]['image_source'] = 'local_ai_preview';

        // Prefer the configured image-search provider over model-provided URLs. LLMs can
        // return official page URLs, CDN URLs that block hotlinking, or outdated image URLs.
        $label = trim(implode(' ', array_filter([
            phone_finder_text($candidate['brand'] ?? '', 80),
            phone_finder_text($candidate['model'] ?? '', 120),
            phone_finder_text($candidate['variant'] ?? '', 80),
        ])));

        $found = phone_finder_search_product_image($candidate);
        if (is_array($found) && !empty($found['url'])) {
            $cached = phone_finder_download_preview_image((string) $found['url'], $label);
            if (is_array($cached) && !empty($cached['url'])) {
                $ai['recommendations'][$index]['request_image'] = $cached;
                $ai['recommendations'][$index]['image_url'] = $cached['url'];
                $ai['recommendations'][$index]['image_source'] = $found['source'] ?? 'image_search_cached';
                $ai['recommendations'][$index]['image_context'] = $found['context'] ?? '';
                $ai['recommendations'][$index]['image_title'] = $found['title'] ?? '';
                $ai['recommendations'][$index]['image_original_url'] = $found['url'];
                continue;
            }
        }

        // Do not call OpenAI Images API here. Only real web images are used.

        $existing = phone_finder_candidate_image_url($candidate);
        if ($existing !== '') {
            $cached = phone_finder_download_preview_image($existing, $label);
            if (is_array($cached) && !empty($cached['url'])) {
                $ai['recommendations'][$index]['request_image'] = $cached;
                $ai['recommendations'][$index]['image_url'] = $cached['url'];
                $ai['recommendations'][$index]['image_source'] = 'ai_url_cached';
                continue;
            }
        }

        // Keep the already-created neutral local SVG placeholder. It is a real local file,
        // not an external hotlink and not a browser-blocked data URI.
        $ai['recommendations'][$index]['request_image'] = $localPreview;
        $ai['recommendations'][$index]['image_url'] = $localPreview['url'];
        $ai['recommendations'][$index]['image_fallback_url'] = $localPreview['url'];
        $ai['recommendations'][$index]['image_source'] = 'local_ai_preview';
    }

    return $ai;
}

function phone_finder_import_request_image(PDO $pdo, int $requestId, array $candidate): array
{
    $name = trim(implode(' ', array_filter([
        phone_finder_text($candidate['brand'] ?? '', 80),
        phone_finder_text($candidate['model'] ?? '', 120),
        phone_finder_text($candidate['variant'] ?? '', 120),
    ])));

    // Reuse locally cached preview images when available; this avoids a second remote
    // download at request time and prevents hotlink/CDN failures from breaking admin.
    $meta = null;
    $searchMeta = null;
    if (isset($candidate['request_image']) && is_array($candidate['request_image'])) {
        $meta = phone_finder_copy_local_image_meta_to_request($requestId, $candidate['request_image'], $name);
        if (is_array($meta) && !empty($candidate['image_original_url'])) {
            $meta['original_url'] = (string) $candidate['image_original_url'];
        }
        if (is_array($meta) && !empty($candidate['image_source'])) {
            $meta['source'] = (string) $candidate['image_source'];
        }
    }

    // Prefer image-search providers when configured, because model-provided URLs are
    // often not direct downloadable product images. Fall back to model URL, then SVG.
    if ($meta === null) {
        $searchMeta = phone_finder_search_product_image($candidate);
        $remote = is_array($searchMeta) ? phone_finder_validate_remote_image_url($searchMeta['url'] ?? '') : '';
        $meta = $remote !== '' ? phone_finder_download_request_image($requestId, $remote) : null;
    }

    if ($meta === null) {
        $remote = phone_finder_candidate_image_url($candidate);
        $meta = $remote !== '' ? phone_finder_download_request_image($requestId, $remote) : null;
    }

    if ($meta !== null && is_array($searchMeta)) {
        $meta['source'] = $searchMeta['source'] ?? $meta['source'];
        $meta['context'] = $searchMeta['context'] ?? '';
        $meta['title'] = $searchMeta['title'] ?? '';
    }

    // Do not call OpenAI Images API at request time. If no real web image was
    // found, keep a neutral local placeholder so the request card never breaks.

    if ($meta === null) {
        $preview = phone_finder_write_ai_preview_image($candidate);
        $root = function_exists('base_path') ? base_path() : dirname(__DIR__);
        $sourceFull = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($preview['path'] ?? ''));
        if (is_file($sourceFull)) {
            $filename = 'request-' . $requestId . '.svg';
            $targetFull = phone_finder_request_images_dir() . DIRECTORY_SEPARATOR . $filename;
            @copy($sourceFull, $targetFull);
            if (is_file($targetFull)) {
                $meta = [
                    'source' => 'local_ai_preview',
                    'url' => phone_finder_request_image_public_url($filename),
                    'path' => phone_finder_public_relative_path(phone_finder_request_images_public_base() . '/' . $filename),
                    'original_url' => '',
                    'label' => $name,
                ];
            }
        }
    }
    if ($meta === null) {
        $meta = phone_finder_write_request_placeholder_image($requestId, $name);
    }
    if (($meta['label'] ?? '') === '') {
        $meta['label'] = $name;
    }

    $candidate['request_image'] = $meta;
    $candidate['image_url'] = $meta['url'];
    $candidate['image_fallback_url'] = phone_finder_placeholder_public_url();
    $candidate['image_source'] = $meta['source'];

    $stmt = $pdo->prepare('UPDATE product_sourcing_requests SET ai_result_json = :ai_result_json WHERE id = :id LIMIT 1');
    $stmt->execute([
        'ai_result_json' => json_encode($candidate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'id' => $requestId,
    ]);

    return $candidate;
}

function phone_finder_delete_request_image(array $ai): void
{
    $path = '';
    if (isset($ai['request_image']) && is_array($ai['request_image'])) {
        $path = (string) ($ai['request_image']['path'] ?? '');
    }
    if ($path === '') {
        $path = (string) ($ai['image_local_path'] ?? '');
    }
    $path = phone_finder_public_relative_path($path);
    if ($path === '' || !str_starts_with($path, phone_finder_request_images_public_base() . '/')) {
        return;
    }
    $root = function_exists('base_path') ? base_path() : dirname(__DIR__);
    $full = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $allowedDir = realpath(phone_finder_request_images_dir());
    $realFile = realpath($full);
    if ($allowedDir !== false && $realFile !== false && str_starts_with($realFile, $allowedDir) && is_file($realFile)) {
        @unlink($realFile);
    }
}

function phone_finder_insert_request(PDO $pdo, array $data, ?array $currentUser = null): int
{
    phone_finder_ensure_tables($pdo);

    $preferences = $data['preferences'] ?? [];
    $aiResult = $data['ai_result'] ?? null;
    $productId = isset($data['existing_product_id']) ? (int) $data['existing_product_id'] : null;
    if ($productId !== null && $productId <= 0) {
        $productId = null;
    }

    $customerName = phone_finder_text($data['customer_name'] ?? ($currentUser['name'] ?? ''), 190) ?: null;
    $customerEmail = phone_finder_text($data['customer_email'] ?? ($currentUser['email'] ?? ''), 190) ?: null;
    if ($customerEmail !== null && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $customerEmail = null;
    }
    $customerPhone = phone_finder_text($data['customer_phone'] ?? '', 80) ?: null;

    $model = phone_finder_text($data['requested_model'] ?? '', 190);
    if ($model === '') {
        throw new RuntimeException('Requested model is required.');
    }

    $stmt = $pdo->prepare('INSERT INTO product_sourcing_requests
        (user_id, session_key, customer_name, customer_email, customer_phone, source, existing_product_id, requested_brand, requested_model, requested_variant, preferences_json, ai_result_json, status)
        VALUES (:user_id, :session_key, :customer_name, :customer_email, :customer_phone, :source, :existing_product_id, :requested_brand, :requested_model, :requested_variant, :preferences_json, :ai_result_json, :status)');
    $stmt->execute([
        'user_id' => isset($currentUser['id']) ? (int) $currentUser['id'] : null,
        'session_key' => phone_finder_session_key(),
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'source' => phone_finder_text($data['source'] ?? 'ai', 40) ?: 'ai',
        'existing_product_id' => $productId,
        'requested_brand' => phone_finder_text($data['requested_brand'] ?? '', 120) ?: null,
        'requested_model' => $model,
        'requested_variant' => phone_finder_text($data['requested_variant'] ?? '', 190) ?: null,
        'preferences_json' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'ai_result_json' => $aiResult === null ? null : json_encode($aiResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 'new',
    ]);

    return (int) $pdo->lastInsertId();
}

function phone_finder_decode_json_field($raw): array
{
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
}
