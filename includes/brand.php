<?php

declare(strict_types=1);

/** 品牌与站点信息（优先读数据库 system_settings，其次文件默认） */
const BRAND_NAME_DEFAULT = '清账系统';
const BRAND_LOGO_DEFAULT = '/img/logo.png';
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
    $v = trim(SettingsService::get('app_version', '1.2.0'));
    return $v !== '' ? $v : '1.2.0';
}
