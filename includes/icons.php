<?php

declare(strict_types=1);

/** 返回 inline SVG 图标 markup */
function svgIcon(string $name, string $class = 'nav-icon'): string
{
    $paths = [
        'dashboard' => <<<'SVG'
<rect x="3" y="3" width="7" height="9" rx="1"/>
<rect x="14" y="3" width="7" height="5" rx="1"/>
<rect x="14" y="12" width="7" height="9" rx="1"/>
<rect x="3" y="16" width="7" height="5" rx="1"/>
SVG,
        'statistics' => <<<'SVG'
<line x1="18" y1="20" x2="18" y2="10"/>
<line x1="12" y1="20" x2="12" y2="4"/>
<line x1="6" y1="20" x2="6" y2="14"/>
SVG,
        'orders' => <<<'SVG'
<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
<rect x="9" y="3" width="6" height="4" rx="1"/>
<line x1="9" y1="12" x2="15" y2="12"/>
<line x1="9" y1="16" x2="13" y2="16"/>
SVG,
        'withdrawals' => <<<'SVG'
<rect x="2" y="5" width="20" height="14" rx="2"/>
<line x1="2" y1="10" x2="22" y2="10"/>
SVG,
        'password' => <<<'SVG'
<rect x="3" y="11" width="18" height="11" rx="2"/>
<path d="M7 11V7a5 5 0 0110 0v4"/>
SVG,
        'registrations' => <<<'SVG'
<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
<polyline points="14 2 14 8 20 8"/>
<line x1="12" y1="18" x2="12" y2="12"/>
<line x1="9" y1="15" x2="15" y2="15"/>
SVG,
        'staff' => <<<'SVG'
<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
<circle cx="12" cy="7" r="4"/>
SVG,
        'employees' => <<<'SVG'
<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
<circle cx="9" cy="7" r="4"/>
<path d="M23 21v-2a4 4 0 00-3-3.87"/>
<path d="M16 3.13a4 4 0 010 7.75"/>
SVG,
        'customers' => <<<'SVG'
<rect x="4" y="2" width="16" height="20" rx="2"/>
<path d="M9 22v-4h6v4"/>
<line x1="8" y1="6" x2="8.01" y2="6"/>
<line x1="12" y1="6" x2="12.01" y2="6"/>
<line x1="16" y1="6" x2="16.01" y2="6"/>
<line x1="8" y1="10" x2="8.01" y2="10"/>
<line x1="12" y1="10" x2="12.01" y2="10"/>
<line x1="16" y1="10" x2="16.01" y2="10"/>
<line x1="8" y1="14" x2="8.01" y2="14"/>
<line x1="12" y1="14" x2="12.01" y2="14"/>
<line x1="16" y1="14" x2="16.01" y2="14"/>
SVG,
        'settings' => <<<'SVG'
<circle cx="12" cy="12" r="3"/>
<path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
SVG,
        'business_types' => <<<'SVG'
<circle cx="12" cy="12" r="3"/>
<path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
SVG,
        'report' => <<<'SVG'
<circle cx="12" cy="12" r="10"/>
<line x1="12" y1="8" x2="12" y2="16"/>
<line x1="8" y1="12" x2="16" y2="12"/>
SVG,
        'logout' => <<<'SVG'
<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
<polyline points="16 17 21 12 16 7"/>
<line x1="21" y1="12" x2="9" y2="12"/>
SVG,
        'arrow-left' => <<<'SVG'
<line x1="19" y1="12" x2="5" y2="12"/>
<polyline points="12 19 5 12 12 5"/>
SVG,
        'empty-orders' => <<<'SVG'
<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
<rect x="9" y="3" width="6" height="4" rx="1"/>
SVG,
        'empty-withdrawals' => <<<'SVG'
<rect x="2" y="5" width="20" height="14" rx="2"/>
<line x1="2" y1="10" x2="22" y2="10"/>
SVG,
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    $classAttr = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

    return sprintf(
        '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">%s</svg>',
        $classAttr,
        $paths[$name]
    );
}
