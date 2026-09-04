<?php

/** local=本地存储（COS 未配置时自动启用）| cos=腾讯云 COS */
return [
    'driver'     => getenv('STORAGE_DRIVER') ?: 'auto',
    'local_dir'  => __DIR__ . '/../uploads/orders',
    'local_url'  => '/uploads/orders',
];
