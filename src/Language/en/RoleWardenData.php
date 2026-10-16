<?php

declare(strict_types=1);

// The seeded roles and permissions, as stored. A translation goes in
// Language/{locale}/RoleWardenData.php with the same keys.
return [
    'roles' => [
        'super-admin' => ['name' => 'Super admin', 'description' => 'Bypasses role checks; still subject to user-level denials.'],
        'admin' => ['name' => 'Admin', 'description' => 'Manages users, roles and permissions.'],
        'user' => ['name' => 'User', 'description' => 'Default role with no administrative permissions.'],
    ],
    'areas' => [
        'users' => 'Users',
        'roles' => 'Roles',
        'permissions' => 'Permissions',
        'settings' => 'Settings',
        'sessions' => 'Sessions',
        'activity' => 'Activity',
        'security' => 'Security',
        'appearance' => 'Appearance',
    ],
    'permissions' => [
        'users' => [
            'view' => 'View users',
            'create' => 'Create users',
            'update' => 'Edit users',
            'delete' => 'Delete users',
            'activate' => 'Activate and deactivate users',
            'import' => 'Import users from a CSV file',
        ],
        'roles' => [
            'view' => 'View roles',
            'create' => 'Create roles',
            'update' => 'Edit roles',
            'delete' => 'Delete roles',
            'assign' => 'Assign roles to users',
        ],
        'permissions' => [
            'view' => 'View permissions',
            'override' => 'Grant or deny a permission to a single user',
        ],
        'settings' => [
            'view' => 'View module settings',
            'update' => 'Change module settings',
        ],
        'sessions' => [
            'view' => 'See the signed-in sessions of other users',
            'revoke' => 'Sign other users out of their sessions',
        ],
        'activity' => [
            'view' => 'Read the activity log',
        ],
        'security' => [
            'alerts' => 'Receive security alert emails (too many failed sign-ins)',
        ],
        'appearance' => [
            'update' => 'Change the panel theme',
        ],
    ],
];
