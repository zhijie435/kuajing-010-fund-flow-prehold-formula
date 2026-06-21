<?php

if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if ($value === 'null') return null;
        return $value;
    }
}

return [
    'db' => [
        'path' => env('DB_PATH', __DIR__ . '/../database/database.sqlite'),
    ],
    'app' => [
        'name' => env('APP_NAME', '电商订单库存后台'),
        'debug' => env('APP_DEBUG', true),
        'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),
    ],
    'cors' => [
        'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
        'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),
        'allowed_headers' => explode(',', env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With')),
    ],
    'fund_flow' => [
        'default_currency' => env('FUND_FLOW_DEFAULT_CURRENCY', 'CNY'),
        'default_operator' => env('FUND_FLOW_DEFAULT_OPERATOR', 'system'),
        'flow_no_prefix' => env('FUND_FLOW_NO_PREFIX', 'FF'),
        'min_amount' => (float)env('FUND_FLOW_MIN_AMOUNT', 0.01),
        'max_amount' => (float)env('FUND_FLOW_MAX_AMOUNT', 99999999.99),
        'allow_negative_balance' => env('FUND_FLOW_ALLOW_NEGATIVE_BALANCE', true),
    ],
    'withholding' => [
        'default_operator' => env('WITHHOLDING_DEFAULT_OPERATOR', 'system'),
        'default_initial_status' => (int)env('WITHHOLDING_DEFAULT_INITIAL_STATUS', 1),
        'max_batch_size' => (int)env('WITHHOLDING_MAX_BATCH_SIZE', 100),
        'allow_negative_result' => env('WITHHOLDING_ALLOW_NEGATIVE_RESULT', false),
        'precision' => (int)env('WITHHOLDING_PRECISION', 2),
        'auto_create_fund_flow' => env('WITHHOLDING_AUTO_CREATE_FUND_FLOW', true),
    ],
];
