<?php

return [
    'db' => [
        'path' => __DIR__ . '/../database/database.sqlite',
    ],
    'app' => [
        'name' => '电商订单库存后台',
        'debug' => true,
        'timezone' => 'Asia/Shanghai',
    ],
    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    ],
];
