<?php

declare(strict_types=1);

/** 结算倍率已并入「业务类型」页，保留此入口以免旧书签失效 */
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('business_types');
redirect('/admin/business_types.php#settlement');
