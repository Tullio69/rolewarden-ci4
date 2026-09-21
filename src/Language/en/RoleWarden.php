<?php

declare(strict_types=1);

return [
    'accessDenied' => 'You do not have permission to access this page.',
    'protection' => [
        'lastSuperAdmin' => 'This would leave the system without an active super admin.',
        'hierarchyCycle' => 'A role cannot inherit from itself or from one of its descendants.',
        'parentMissing' => 'The parent role does not exist.',
        'systemRecord' => 'System records cannot be deleted.',
    ],
];
