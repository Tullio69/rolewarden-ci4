<?php

declare(strict_types=1);

// The demo's roles and permissions as DemoInstaller stores them; keep the two in step.
return [
    'roles' => [
        'demo-reader' => ['name' => 'Reader', 'description' => 'Reads the demo documents.'],
        'demo-author' => ['name' => 'Author', 'description' => 'Writes demo documents and edits their own.'],
        'demo-approver' => ['name' => 'Approver', 'description' => 'Approves and deletes demo documents.'],
    ],
    'areas' => ['documents' => 'Documents'],
    'actions' => ['approve' => 'approve'],
    'permissions' => [
        'documents' => [
            'view' => 'See the demo documents',
            'create' => 'Write demo documents',
            'update' => 'Edit and submit your own demo documents',
            'approve' => 'Approve submitted demo documents',
            'delete' => 'Delete any demo document',
        ],
    ],
];
