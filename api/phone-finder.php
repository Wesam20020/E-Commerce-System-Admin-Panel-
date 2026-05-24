<?php
require __DIR__ . '/../includes/bootstrap.php';

$localAiConfig = __DIR__ . '/../includes/local_ai_config.php';
if (is_file($localAiConfig)) {
    require_once $localAiConfig;
}

require_once __DIR__ . '/../includes/phone_matcher.php';
require_once __DIR__ . '/../includes/ai_phone_recommender.php';

if (request_method() !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = request_input();
verify_csrf_or_fail($input['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));

$action = phone_finder_text($input['action'] ?? 'find', 40);

try {
    phone_finder_ensure_tables($pdo);

    if ($action === 'find') {
        $preferences = phone_finder_normalize_preferences(is_array($input['preferences'] ?? null) ? $input['preferences'] : $input);
        $forceAi = !empty($input['force_ai']);
        $catalogMatches = phone_finder_find_catalog_matches($pdo, $preferences, $siteCurrency, 6);
        $autoAi = phone_finder_should_use_ai($catalogMatches);
        $shouldUseAi = $forceAi || $autoAi;
        $ai = [
            'ok' => false,
            'configured' => phone_finder_ai_configured(),
            'used' => false,
            'message' => '',
            'summary' => '',
            'recommendations' => [],
        ];

        if ($shouldUseAi) {
            $ai = phone_finder_ai_recommend($pdo, $preferences, $catalogMatches);
            $ai = phone_finder_enrich_ai_recommendations_with_images($ai);
            $ai['used'] = true;
            $ai['forced'] = $forceAi;
            $ai['configured'] = phone_finder_ai_configured();
            $ai['image_search_configured'] = phone_finder_image_search_configured();
            $ai['image_provider'] = phone_finder_image_provider();
        }

        json_response([
            'ok' => true,
            'mode' => $shouldUseAi ? 'ai_or_mixed' : 'catalog',
            'preferences' => $preferences,
            'catalog_matches' => $catalogMatches,
            'ai' => $ai,
            'ai_available' => phone_finder_ai_configured(),
            'image_search_configured' => phone_finder_image_search_configured(),
            'image_provider' => phone_finder_image_provider(),
            'ai_auto_used' => $autoAi,
            'ai_forced' => $forceAi,
            'message' => $catalogMatches ? 'Suitable phones found.' : 'No close match was found yet.',
        ]);
    }

    if ($action === 'request') {
        $preferences = phone_finder_normalize_preferences(is_array($input['preferences'] ?? null) ? $input['preferences'] : []);
        $source = phone_finder_text($input['source'] ?? 'ai', 40) ?: 'ai';
        $candidate = is_array($input['candidate'] ?? null) ? $input['candidate'] : [];

        $data = [
            'source' => $source,
            'existing_product_id' => $input['existing_product_id'] ?? null,
            'requested_brand' => $candidate['brand'] ?? ($input['requested_brand'] ?? ''),
            'requested_model' => $candidate['model'] ?? ($input['requested_model'] ?? ''),
            'requested_variant' => $candidate['variant'] ?? ($input['requested_variant'] ?? ''),
            'preferences' => $preferences,
            'ai_result' => $candidate ?: null,
            'customer_name' => $input['customer_name'] ?? '',
            'customer_email' => '',
            'customer_phone' => $input['customer_phone'] ?? '',
        ];

        // Dashboard-only request: this action stores the sourcing request in product_sourcing_requests.
        // It intentionally does not queue or send any email notification.

        $id = phone_finder_insert_request($pdo, $data, $currentUser);
        $candidate = phone_finder_import_request_image($pdo, $id, $candidate ?: $data['ai_result'] ?: []);

        if (function_exists('admin_log_activity')) {
            // This function is normally available only inside the admin console.
        }

        json_response([
            'ok' => true,
            'request_id' => $id,
            'candidate' => $candidate,
            'message' => 'Thanks. Your request has been sent to the Phonix team.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Unknown action.'], 422);
} catch (Throwable $e) {
    error_log('[Phone Finder API] ' . $e->getMessage());
    json_response([
        'ok' => false,
        'message' => app_debug() ? $e->getMessage() : 'The phone finder could not complete this request.',
    ], 500);
}
