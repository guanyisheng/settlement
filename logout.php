<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/Auth.php';

Auth::startSession();
Auth::logout();
redirect('/login.php');
