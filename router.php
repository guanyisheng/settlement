<?php
/**
 * PHP 内置服务器路由
 * 用法: php -S localhost:8080 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 静态文件直接返回
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// 根路径
if ($uri === '/') {
    require __DIR__ . '/index.php';
    return true;
}

// 其他 PHP 文件
$file = __DIR__ . $uri;
if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
