<?php

declare(strict_types=1);

return [
    'roles' => [
        'demo-reader' => ['name' => 'Lettore', 'description' => 'Legge i documenti della demo.'],
        'demo-author' => ['name' => 'Autore', 'description' => 'Scrive documenti della demo e modifica i propri.'],
        'demo-approver' => ['name' => 'Approvatore', 'description' => 'Approva ed elimina i documenti della demo.'],
    ],
    'areas' => ['documents' => 'Documenti'],
    'permissions' => [
        'documents' => [
            'view' => 'Vedere i documenti della demo',
            'create' => 'Scrivere documenti della demo',
            'update' => 'Modificare e inviare i propri documenti della demo',
            'approve' => 'Approvare i documenti della demo inviati',
            'delete' => 'Eliminare qualsiasi documento della demo',
        ],
    ],
];
