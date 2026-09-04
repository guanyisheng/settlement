<?php

return [
    'secret_id'  => getenv('COS_SECRET_ID') ?: '',
    'secret_key' => getenv('COS_SECRET_KEY') ?: '',
    'region'     => 'ap-guangzhou',
    'bucket'     => 'your-bucket-name',
    'prefix'     => 'orders/',
    'app_id'     => '',
];
