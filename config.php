<?php
return [
    'app_env' => env_value('PHONIX_APP_ENV', 'production'),
    'app_debug' => env_bool('PHONIX_APP_DEBUG', false),
    'allow_setup' => env_bool('PHONIX_ALLOW_SETUP', false),
    'allow_debug_endpoints' => env_bool('PHONIX_ALLOW_DEBUG_ENDPOINTS', false),
    'db_host' => env_value('PHONIX_DB_HOST', 'localhost'),
    'db_port' => (int) env_value('PHONIX_DB_PORT', 3306),
    'db_name' => env_value('PHONIX_DB_NAME', 'u121487499_phonex_store'),
    'db_user' => env_value('PHONIX_DB_USER', 'u121487499_phonex_store'),
    'db_pass' => env_value('PHONIX_DB_PASS', 'Ghaith@0552'),
    'db_charset' => env_value('PHONIX_DB_CHARSET', 'utf8mb4'),
];
