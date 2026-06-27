<?php

declare(strict_types=1);

return [
    /*
     | All permissions in the system. Authorization is permission-based
     | (never role-name checks), so the matrix below can change freely.
     */
    'permissions' => [
        'catalog.view',
        'catalog.manage',
        'orders.view',
        'orders.manage',
        'payments.view',
        'payments.manage',
        'marketing.manage',
        'settings.manage',
        'users.manage',
        'maintenance.manage',
        'audit.view',
        // Owner-only developer console (artisan command buttons, logs, errors).
        // Granted only via the owner '*' wildcard — never added to admin below.
        'developer.access',
    ],

    /*
     | Role => permissions. '*' means "every permission" (owner). Mirrors
     | docs/feature-plan/MASTER-PLAN.md §6.
     */
    'roles' => [
        'owner' => ['*'],
        'admin' => [
            'catalog.view', 'catalog.manage',
            'orders.view', 'orders.manage',
            'payments.view', 'payments.manage',
            'marketing.manage', 'settings.manage',
            'users.manage', 'audit.view',
        ],
        'manager' => ['catalog.view', 'catalog.manage', 'orders.view', 'orders.manage'],
        'sub-admin' => ['catalog.view', 'catalog.manage', 'orders.view'],
        'marketer' => ['marketing.manage'],
        'editor' => ['catalog.view', 'catalog.manage'],
    ],

    /*
     | Bootstrap accounts (values come from env, never committed). The owner is
     | the highest legitimate role; admin is the day-to-day client admin.
     */
    'owner_email' => env('OWNER_EMAIL'),
    'owner_bootstrap_password' => env('OWNER_BOOTSTRAP_PASSWORD'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@gmail.com'),
    'admin_bootstrap_password' => env('ADMIN_PASSWORD'),

    /*
     | Where the account-security middleware sends users who must finish setup.
     | These map to the existing settings pages.
     */
    'password_change_url' => '/settings/password',
    'two_factor_setup_url' => '/settings/two-factor',
];
