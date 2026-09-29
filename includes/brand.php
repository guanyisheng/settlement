<?php

declare(strict_types=1);

/** 品牌与站点信息（优先读数据库 system_settings，其次文件默认） */
const BRAND_NAME_DEFAULT = '清账系统';
const BRAND_LOGO_DEFAULT = 'https://wp-1301153132.cos.ap-chengdu.myqcloud.com/shasha/orders/logo.png';
const BRAND_THEME_COLOR_DEFAULT = '#001A72';

require_once __DIR__ . '/SettingsService.php';

function brandName(): string
{
    return SettingsService::get('brand_name', BRAND_NAME_DEFAULT);
}

function brandLogo(): string
{
    return SettingsService::get('brand_logo', BRAND_LOGO_DEFAULT);
}

function brandThemeColor(): string
{
    return SettingsService::get('brand_theme_color', BRAND_THEME_COLOR_DEFAULT);
}

/** @deprecated 使用 brandName() */
const BRAND_NAME = BRAND_NAME_DEFAULT;
/** @deprecated 使用 brandLogo() */
const BRAND_LOGO = BRAND_LOGO_DEFAULT;
/** @deprecated 使用 brandThemeColor() */
const BRAND_THEME_COLOR = BRAND_THEME_COLOR_DEFAULT;

function brandTitle(string $page = ''): string
{
    $name = brandName();
    if ($page === '') {
        return $name;
    }
    return $page . ' - ' . $name;
}

/** 系统版本号（可在后台系统设置修改） */
function appVersion(): string
{
    $v = trim(SettingsService::get('app_version', '3.0.1-finance'));
    return $v !== '' ? $v : '3.0.1-finance';
}

/**
 * 静态资源构建号：版本 + 关键 CSS/JS 文件时间，变化即强制浏览器拉新缓存。
 */
function assetBuildId(): string
{
    static $build = null;
    if ($build !== null) {
        return $build;
    }
    $root = dirname(__DIR__);
    $times = [0];
    foreach ([
        $root . '/admin/assets/css/admin.css',
        $root . '/admin/assets/css/finance-dash.css',
        $root . '/admin/assets/js/img-preview.js',
        $root . '/admin/assets/js/asset-refresh.js',
        $root . '/admin/assets/js/ajax-nav.js',
        $root . '/customer/assets/css/app.css',
        $root . '/customer/assets/css/activity-h5.css',
        $root . '/customer/assets/js/lottery-wheel.js',
    ] as $path) {
        if (is_file($path)) {
            $times[] = (int) filemtime($path);
        }
    }
    $build = appVersion() . '.' . max($times);
    return $build;
}

/** 带防缓存参数的后台静态资源 URL */
function adminAssetUrl(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $sep = str_contains($path, '?') ? '&' : '?';
    return $path . $sep . 'v=' . rawurlencode(assetBuildId());
}
