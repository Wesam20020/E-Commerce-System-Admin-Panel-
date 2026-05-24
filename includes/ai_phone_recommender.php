<?php
require_once __DIR__ . '/phone_matcher.php';

function phone_finder_ai_provider(): string
{
    $provider = strtolower(trim((string) env_value('PHONIX_AI_PROVIDER', env_value('AI_PROVIDER', 'openai'))));
    return $provider !== '' ? $provider : 'openai';
}

function phone_finder_ai_configured(): bool
{
    return phone_finder_ai_api_key() !== '';
}

function phone_finder_ai_api_key(): string
{
    $provider = phone_finder_ai_provider();

    if ($provider === 'openai') {
        $key = env_value('PHONIX_OPENAI_API_KEY', '');
        if ($key === '') {
            $key = env_value('OPENAI_API_KEY', '');
        }
        return trim((string) $key);
    }

    $key = env_value('PHONIX_GEMINI_API_KEY', '');
    if ($key === '') {
        $key = env_value('GEMINI_API_KEY', '');
    }
    if ($key === '') {
        $key = env_value('GOOGLE_API_KEY', '');
    }

    return trim((string) $key);
}

function phone_finder_ai_model(): string
{
    $provider = phone_finder_ai_provider();

    if ($provider === 'openai') {
        return trim((string) env_value('PHONIX_OPENAI_MODEL', 'gpt-4.1-mini')) ?: 'gpt-4.1-mini';
    }

    return trim((string) env_value('PHONIX_GEMINI_MODEL', 'gemini-2.5-flash')) ?: 'gemini-2.5-flash';
}

function phone_finder_openai_endpoint(): string
{
    return trim((string) env_value('PHONIX_OPENAI_RESPONSES_URL', 'https://api.openai.com/v1/responses')) ?: 'https://api.openai.com/v1/responses';
}

function phone_finder_gemini_endpoint(): string
{
    $base = trim((string) env_value('PHONIX_GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta')) ?: 'https://generativelanguage.googleapis.com/v1beta';
    $base = rtrim($base, '/');
    $model = rawurlencode(phone_finder_ai_model());
    return $base . '/models/' . $model . ':generateContent';
}

function phone_finder_ai_schema(): array
{
    // Gemini's REST responseSchema accepts a limited OpenAPI/JSON-schema subset.
    // Do not send unsupported keywords such as additionalProperties, minItems, or maxItems.
    return [
        'type' => 'object',
        'properties' => [
            'summary' => [
                'type' => 'string',
                'description' => 'A concise explanation of the recommendation strategy.',
            ],
            'recommendations' => [
                'type' => 'array',
                'description' => 'Practical smartphone sourcing suggestions.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'brand' => ['type' => 'string'],
                        'model' => ['type' => 'string'],
                        'variant' => ['type' => 'string'],
                        'why_it_matches' => ['type' => 'string'],
                        'key_specs' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'estimated_price_range_try' => ['type' => 'string'],
                        'sourcing_note' => ['type' => 'string'],
                        'image_search_query' => ['type' => 'string'],
                        'image_url' => ['type' => 'string', 'description' => 'Always return an empty string. The server resolves product images through a verified image provider.'],
                        'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    ],
                    'required' => ['brand', 'model', 'variant', 'why_it_matches', 'key_specs', 'estimated_price_range_try', 'sourcing_note', 'image_search_query', 'image_url', 'confidence'],
                ],
            ],
        ],
        'required' => ['summary', 'recommendations'],
    ];
}


function phone_finder_openai_schema(): array
{
    // OpenAI Structured Outputs with strict=true requires every object schema to
    // explicitly disallow extra keys. Keep the Gemini schema separate because
    // Gemini rejects unsupported schema keywords such as additionalProperties.
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'summary' => [
                'type' => 'string',
                'description' => 'A concise explanation of the recommendation strategy.',
            ],
            'recommendations' => [
                'type' => 'array',
                'description' => 'Practical smartphone sourcing suggestions.',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'brand' => ['type' => 'string'],
                        'model' => ['type' => 'string'],
                        'variant' => ['type' => 'string'],
                        'why_it_matches' => ['type' => 'string'],
                        'key_specs' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'estimated_price_range_try' => ['type' => 'string'],
                        'sourcing_note' => ['type' => 'string'],
                        'image_search_query' => ['type' => 'string'],
                        'image_url' => ['type' => 'string', 'description' => 'Always return an empty string. The server resolves product images through a verified image provider.'],
                        'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    ],
                    'required' => ['brand', 'model', 'variant', 'why_it_matches', 'key_specs', 'estimated_price_range_try', 'sourcing_note', 'image_search_query', 'image_url', 'confidence'],
                ],
            ],
        ],
        'required' => ['summary', 'recommendations'],
    ];
}

function phone_finder_ai_compact_array(array $data, int $maxStringLength = 120): array
{
    $clean = [];

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $clean[$key] = phone_finder_ai_compact_array($value, $maxStringLength);
            continue;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            $clean[$key] = $value;
            continue;
        }

        $text = trim((string) $value);
        if ($text === '') {
            continue;
        }

        $clean[$key] = phone_finder_text($text, $maxStringLength);
    }

    return $clean;
}

function phone_finder_ai_prompt(array $preferences, array $nearCatalog): string
{
    $compactPreferences = phone_finder_ai_compact_array($preferences, 120);
    $compactCatalog = [];

    foreach (array_slice($nearCatalog, 0, 3) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $compactCatalog[] = phone_finder_ai_compact_array([
            'name' => $item['name'] ?? '',
            'brand' => $item['brand'] ?? '',
            'price_try' => $item['price_try'] ?? $item['price'] ?? '',
            'score' => $item['score'] ?? '',
        ], 80);
    }

    return json_encode([
        'task' => 'Recommend exactly 3 real smartphones for a Turkish phone store to source. Return compact JSON only.',
        'rules' => [
            'Real phones only; no fake models or fake prices.',
            'Do not claim live stock or final price.',
            'Keep every explanation short: max 18 words.',
            'For image_url: always return an empty string. Do not invent or guess product image URLs.',
            'For image_search_query: write a clean search query like brand + model + variant + official product image.',
            'Use English.',
        ],
        'market' => 'Turkey',
        'currency' => 'TRY',
        'preferences' => $compactPreferences,
        'avoid_if_already_catalog_match' => $compactCatalog,
        'json_shape' => [
            'summary' => 'short string',
            'recommendations' => [
                [
                    'brand' => 'string',
                    'model' => 'string',
                    'variant' => 'short string',
                    'why_it_matches' => 'short string',
                    'key_specs' => ['spec 1', 'spec 2', 'spec 3'],
                    'estimated_price_range_try' => 'short string',
                    'sourcing_note' => 'short string',
                    'image_search_query' => 'brand model variant official product image',
                    'image_url' => '',
                    'confidence' => 'medium',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function phone_finder_ai_extract_text($node): string
{
    if (is_string($node)) {
        return $node;
    }
    if (!is_array($node)) {
        return '';
    }
    if (isset($node['output_text']) && is_string($node['output_text'])) {
        return $node['output_text'];
    }
    if (($node['type'] ?? '') === 'output_text' && isset($node['text']) && is_string($node['text'])) {
        return $node['text'];
    }
    if (isset($node['text']) && is_string($node['text'])) {
        return $node['text'];
    }
    $parts = [];
    foreach ($node as $value) {
        $text = phone_finder_ai_extract_text($value);
        if ($text !== '') {
            $parts[] = $text;
        }
    }
    return implode("\n", $parts);
}

function phone_finder_ai_post_json(string $url, array $headers, array $payload): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'error' => 'Could not check more options right now.',
            'response' => null,
            'raw' => '',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 25,
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($raw) || $raw === '') {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error ?: 'More options are not available right now.',
            'response' => null,
            'raw' => '',
        ];
    }

    $response = json_decode($raw, true);
    if (!is_array($response)) {
        return [
            'ok' => false,
            'status' => $status,
            'error' => 'Suggestion service returned an unreadable response.',
            'response' => null,
            'raw' => $raw,
        ];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'error' => '',
        'response' => $response,
        'raw' => $raw,
    ];
}

function phone_finder_ai_is_list(array $array): bool
{
    if ($array === []) {
        return true;
    }
    return array_keys($array) === range(0, count($array) - 1);
}

function phone_finder_ai_clean_json_candidate(string $text): string
{
    $text = trim($text);

    // Remove common markdown fences while keeping the JSON body.
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $m)) {
        $text = trim($m[1]);
    }

    $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
    $text = preg_replace('/\s*```$/', '', $text) ?? $text;

    // Gemini can occasionally add a short prefix/suffix despite JSON mode.
    $objectStart = strpos($text, '{');
    $objectEnd = strrpos($text, '}');
    $arrayStart = strpos($text, '[');
    $arrayEnd = strrpos($text, ']');

    if ($objectStart !== false && $objectEnd !== false && $objectEnd > $objectStart) {
        $objectSlice = substr($text, $objectStart, $objectEnd - $objectStart + 1);
        if ($arrayStart === false || $objectStart < $arrayStart) {
            $text = $objectSlice;
        }
    } elseif ($arrayStart !== false && $arrayEnd !== false && $arrayEnd > $arrayStart) {
        $text = substr($text, $arrayStart, $arrayEnd - $arrayStart + 1);
    }

    // Remove trailing commas, which are invalid JSON but sometimes appear in model output.
    $text = preg_replace('/,\s*([}\]])/', '$1', $text) ?? $text;

    return trim($text);
}

function phone_finder_ai_parse_json_text(string $text): ?array
{
    $candidates = [];
    $candidates[] = $text;
    $candidates[] = phone_finder_ai_clean_json_candidate($text);

    // Sometimes the response is a JSON string containing escaped JSON.
    $first = json_decode(trim($text), true);
    if (is_string($first)) {
        $candidates[] = $first;
        $candidates[] = phone_finder_ai_clean_json_candidate($first);
    } elseif (is_array($first)) {
        return phone_finder_ai_normalize_decoded($first);
    }

    foreach (array_unique($candidates) as $candidate) {
        $decoded = json_decode(trim($candidate), true);
        if (is_array($decoded)) {
            return phone_finder_ai_normalize_decoded($decoded);
        }
    }

    return null;
}

function phone_finder_ai_normalize_decoded(array $decoded): array
{
    // Accept a plain list of recommendations.
    if (phone_finder_ai_is_list($decoded)) {
        return [
            'summary' => 'Recommended phones based on the selected preferences.',
            'recommendations' => $decoded,
        ];
    }

    // Accept common alternative keys if a model ignores the exact schema.
    foreach (['recommendations', 'phones', 'items', 'suggestions', 'results'] as $key) {
        if (isset($decoded[$key]) && is_array($decoded[$key])) {
            $recommendations = $decoded[$key];
            if (!phone_finder_ai_is_list($recommendations)) {
                $recommendations = array_values($recommendations);
            }
            return [
                'summary' => (string) ($decoded['summary'] ?? $decoded['message'] ?? 'Recommended phones based on the selected preferences.'),
                'recommendations' => $recommendations,
            ];
        }
    }

    // Accept a single recommendation object.
    if (isset($decoded['model']) || isset($decoded['brand'])) {
        return [
            'summary' => 'Recommended phone based on the selected preferences.',
            'recommendations' => [$decoded],
        ];
    }

    return $decoded;
}

function phone_finder_gemini_extract_diagnostic(array $response): string
{
    $finish = $response['candidates'][0]['finishReason'] ?? '';
    $block = $response['promptFeedback']['blockReason'] ?? '';
    $parts = [];
    if (is_string($finish) && $finish !== '') {
        $parts[] = 'finishReason=' . $finish;
    }
    if (is_string($block) && $block !== '') {
        $parts[] = 'blockReason=' . $block;
    }
    return implode(', ', $parts);
}

function phone_finder_gemini_max_output_tokens(): int
{
    $tokens = (int) env_value('PHONIX_GEMINI_MAX_OUTPUT_TOKENS', '4096');
    if ($tokens < 1024) {
        return 1024;
    }
    if ($tokens > 8192) {
        return 8192;
    }
    return $tokens;
}

function phone_finder_gemini_generation_config(bool $withSchema = true, bool $withThinkingConfig = true): array
{
    $config = [
        'temperature' => 0.15,
        'topP' => 0.8,
        'topK' => 20,
        'maxOutputTokens' => phone_finder_gemini_max_output_tokens(),
        'responseMimeType' => 'application/json',
    ];

    // Gemini 2.5 models can spend output budget on thinking.
    // This short sourcing task does not need thinking, so this prevents finishReason=MAX_TOKENS.
    if ($withThinkingConfig && stripos(phone_finder_ai_model(), 'gemini-2.5') !== false) {
        $config['thinkingConfig'] = ['thinkingBudget' => 0];
    }

    if ($withSchema) {
        $config['responseSchema'] = phone_finder_ai_schema();
    }

    return $config;
}

function phone_finder_gemini_payload(string $prompt): array
{
    return [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt . "\n\nReturn only valid compact JSON matching the schema. Exactly 3 recommendations. No markdown."],
                ],
            ],
        ],
        'generationConfig' => phone_finder_gemini_generation_config(true),
    ];
}

function phone_finder_gemini_payload_legacy(string $prompt): array
{
    // Fallback for accounts/models that reject responseSchema.
    // We still request compact JSON via prompt and parse/sanitize the result server-side.
    return [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt . "\n\nReturn ONLY this compact JSON object, exactly 3 items: " . '{"summary":"...","recommendations":[{"brand":"...","model":"...","variant":"...","why_it_matches":"...","key_specs":["...","...","..."],"estimated_price_range_try":"...","sourcing_note":"...","image_search_query":"brand model variant official product image","image_url":"","confidence":"medium"}]}' . " No markdown."],
                ],
            ],
        ],
        'generationConfig' => phone_finder_gemini_generation_config(false),
    ];
}

function phone_finder_gemini_payload_plain(string $prompt): array
{
    // Last-resort fallback if a model/account rejects thinkingConfig or schema-related fields.
    return [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt . "\n\nReturn compact valid JSON only. Exactly 3 recommendations. No markdown."],
                ],
            ],
        ],
        'generationConfig' => phone_finder_gemini_generation_config(false, false),
    ];
}

function phone_finder_gemini_decode_result(array $result): ?array
{
    $response = is_array($result['response'] ?? null) ? $result['response'] : [];

    $text = '';
    if (isset($response['candidates'][0]['content']['parts'])) {
        $text = phone_finder_ai_extract_text($response['candidates'][0]['content']['parts']);
    }
    if ($text === '') {
        $text = phone_finder_ai_extract_text($response);
    }

    if ($text === '') {
        return null;
    }

    return phone_finder_ai_parse_json_text($text);
}

function phone_finder_gemini_recommend(string $apiKey, array $preferences, array $nearCatalog): array
{
    $prompt = phone_finder_ai_prompt($preferences, $nearCatalog);
    $headers = [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ];

    $attempts = [
        ['name' => 'schema', 'payload' => phone_finder_gemini_payload($prompt)],
        ['name' => 'legacy-json', 'payload' => phone_finder_gemini_payload_legacy($prompt)],
        ['name' => 'plain-json', 'payload' => phone_finder_gemini_payload_plain($prompt)],
    ];

    $lastMessage = '';
    foreach ($attempts as $attempt) {
        $result = phone_finder_ai_post_json(phone_finder_gemini_endpoint(), $headers, $attempt['payload']);

        if (!$result['ok']) {
            $message = '';
            if (is_array($result['response'])) {
                $message = $result['response']['error']['message'] ?? '';
            }
            if ($message === '') {
                $message = $result['error'] ?: 'We could not check more options right now.';
            }
            $lastMessage = $message;
            error_log('[Phone Finder Gemini] ' . $attempt['name'] . ' HTTP ' . $result['status'] . ': ' . $message);
            continue;
        }

        $decoded = phone_finder_gemini_decode_result($result);
        if (is_array($decoded)) {
            return ['ok' => true, 'message' => '', 'decoded' => $decoded];
        }

        $response = is_array($result['response'] ?? null) ? $result['response'] : [];
        $diag = phone_finder_gemini_extract_diagnostic($response);
        $text = '';
        if (isset($response['candidates'][0]['content']['parts'])) {
            $text = phone_finder_ai_extract_text($response['candidates'][0]['content']['parts']);
        }
        if ($text === '') {
            $text = phone_finder_ai_extract_text($response);
        }
        error_log('[Phone Finder Gemini] ' . $attempt['name'] . ' parse failed. ' . $diag . ' Text: ' . mb_substr($text, 0, 1200));
        $lastMessage = $diag !== '' ? $diag : 'More options are not available right now.';
    }

    if ($lastMessage !== '') {
        return ['ok' => false, 'message' => 'We could not check more options right now. ' . phone_finder_text($lastMessage, 220), 'decoded' => null];
    }

    return ['ok' => false, 'message' => 'More options are not available right now.', 'decoded' => null];
}

function phone_finder_openai_recommend(string $apiKey, array $preferences, array $nearCatalog): array
{
    $payload = [
        'model' => phone_finder_ai_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => 'You are a sourcing assistant for Phonix, a smartphone e-commerce store in Turkey. Recommend real smartphone models only. Do not claim current stock or final prices. Treat suggestions as items the store may try to source after admin review.',
            ],
            [
                'role' => 'user',
                'content' => phone_finder_ai_prompt($preferences, $nearCatalog),
            ],
        ],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'phone_sourcing_recommendations',
                'schema' => phone_finder_openai_schema(),
                'strict' => true,
            ],
        ],
        'store' => false,
        'max_output_tokens' => 2200,
    ];

    $result = phone_finder_ai_post_json(phone_finder_openai_endpoint(), [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ], $payload);

    if (!$result['ok']) {
        $message = '';
        if (is_array($result['response'])) {
            $message = $result['response']['error']['message'] ?? '';
        }
        if ($message === '') {
            $message = $result['error'] ?: 'We could not check more options right now.';
        }
        error_log('[Phone Finder OpenAI] HTTP ' . $result['status'] . ': ' . $message);
        return ['ok' => false, 'message' => 'We could not check more options right now. ' . phone_finder_text($message, 220), 'decoded' => null];
    }

    $text = phone_finder_ai_extract_text($result['response']);
    $decoded = phone_finder_ai_parse_json_text($text);

    if (!is_array($decoded)) {
        error_log('[Phone Finder OpenAI] Could not parse output text: ' . mb_substr($text, 0, 1000));
        return ['ok' => false, 'message' => 'More options are not available right now.', 'decoded' => null];
    }

    return ['ok' => true, 'message' => '', 'decoded' => $decoded];
}


function phone_finder_ai_sanitize_image_url($value): string
{
    $url = trim((string) $value);
    if ($url === '') {
        return '';
    }
    if (strlen($url) > 900) {
        return '';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'https') {
        return '';
    }
    return $url;
}

function phone_finder_ai_recommend(PDO $pdo, array $preferences, array $catalogMatches = []): array
{
    $apiKey = phone_finder_ai_api_key();
    $provider = phone_finder_ai_provider();

    if ($apiKey === '') {
        $expected = $provider === 'openai' ? 'PHONIX_OPENAI_API_KEY' : 'PHONIX_GEMINI_API_KEY';
        return [
            'ok' => false,
            'configured' => false,
            'provider' => $provider,
            'message' => 'More options are not available right now. Please try again later or contact Phonix support.',
            'summary' => '',
            'recommendations' => [],
        ];
    }

    $nearCatalog = array_map(static function (array $item): array {
        return [
            'name' => $item['name'] ?? '',
            'brand' => $item['brand'] ?? '',
            'price_try' => $item['price'] ?? 0,
            'score' => $item['score'] ?? 0,
            'reason' => implode('; ', $item['reasons'] ?? []),
        ];
    }, array_slice($catalogMatches, 0, 5));

    $ai = $provider === 'openai'
        ? phone_finder_openai_recommend($apiKey, $preferences, $nearCatalog)
        : phone_finder_gemini_recommend($apiKey, $preferences, $nearCatalog);

    if (!$ai['ok']) {
        return [
            'ok' => false,
            'configured' => true,
            'provider' => $provider,
            'message' => $ai['message'] ?: 'We could not check more options right now.',
            'summary' => '',
            'recommendations' => [],
        ];
    }

    $decoded = is_array($ai['decoded'] ?? null) ? $ai['decoded'] : [];
    $recommendations = [];
    foreach (($decoded['recommendations'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $model = phone_finder_text($item['model'] ?? '', 190);
        if ($model === '') {
            continue;
        }
        // Do not trust model-provided product image URLs by default. LLMs often
        // return outdated CDN links, official page URLs instead of direct images, or
        // hotlink-blocked resources. The matcher layer resolves images through the
        // configured image provider and falls back to a local placeholder.
        $allowAiImageUrls = phone_finder_bool(env_value('PHONIX_ALLOW_AI_IMAGE_URLS', '0'));
        $imageUrl = $allowAiImageUrls ? phone_finder_ai_sanitize_image_url($item['image_url'] ?? '') : '';
        if ($imageUrl === '') {
            $imageUrl = 'assets/images/phone-ai-placeholder.svg';
        }

        $recommendations[] = [
            'brand' => phone_finder_text($item['brand'] ?? '', 120),
            'model' => $model,
            'variant' => phone_finder_text($item['variant'] ?? '', 190),
            'why_it_matches' => phone_finder_text($item['why_it_matches'] ?? '', 900),
            'key_specs' => array_values(array_slice(array_filter(array_map(static fn($v): string => phone_finder_text($v, 140), (array) ($item['key_specs'] ?? []))), 0, 8)),
            'estimated_price_range_try' => phone_finder_text($item['estimated_price_range_try'] ?? '', 120),
            'sourcing_note' => phone_finder_text($item['sourcing_note'] ?? '', 400),
            'image_search_query' => phone_finder_text($item['image_search_query'] ?? trim(($item['brand'] ?? '') . ' ' . $model . ' official product image'), 220),
            'image_url' => $imageUrl,
            'confidence' => in_array(($item['confidence'] ?? ''), ['low', 'medium', 'high'], true) ? $item['confidence'] : 'medium',
        ];
    }

    return [
        'ok' => true,
        'configured' => true,
        'provider' => $provider,
        'model' => phone_finder_ai_model(),
        'message' => '',
        'summary' => phone_finder_text($decoded['summary'] ?? '', 600),
        'recommendations' => array_slice($recommendations, 0, 5),
        'raw' => $decoded,
    ];
}
