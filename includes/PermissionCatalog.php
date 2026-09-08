<?php

declare(strict_types=1);

/**
 * 权限点目录（与 permissions 表 code 一致）
 */
class PermissionCatalog
{
    public const ALL = [
        'dashboard.view',
        'report.create',
        'order.view',
        'order.review',
        'order.delete',
        'withdrawal.view',
        'withdrawal.process',
        'registration.review',
        'staff.view',
        'staff.manage',
        'photo.view',
        'photo.download',
        'honor.view',
        'honor.manage',
        'customer.view',
        'customer.manage',
        'business.view',
        'business.manage',
        'stats.view',
        'board.view',
        'user.view',
        'user.manage',
        'user.delete',
        'role.view',
        'role.manage',
        'permission.manage',
        'rate.manage',
        'settings.manage',
        'password.change',
    ];

    /** 菜单项 → 所需任一权限 */
    public const MENU_PERMISSIONS = [
        'dashboard'      => ['dashboard.view'],
        'orders'         => ['order.view', 'order.review'],
        'report'         => ['report.create'],
        'withdrawals'    => ['withdrawal.view', 'withdrawal.process'],
        'password'       => ['password.change'],
        'statistics'     => ['stats.view', 'board.view'],
        'registrations'  => ['registration.review'],
        'staff'          => ['staff.view', 'staff.manage'],
        'users'          => ['staff.view', 'staff.manage', 'user.view', 'user.manage'],
        'employees'      => ['user.view', 'user.manage'],
        'roles'          => ['role.view', 'role.manage'],
        'customers'      => ['customer.view', 'customer.manage'],
        'business_types' => ['business.view', 'business.manage'],
        'rates'          => ['rate.manage'],
        'settings'       => ['settings.manage'],
        'honors'         => ['honor.view', 'honor.manage'],
    ];
}
