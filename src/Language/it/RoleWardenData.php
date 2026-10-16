<?php

declare(strict_types=1);

return [
    'roles' => [
        'super-admin' => ['name' => 'Super admin', 'description' => 'Salta i controlli dei ruoli; restano valide le negazioni sul singolo utente.'],
        'admin' => ['name' => 'Amministratore', 'description' => 'Gestisce utenti, ruoli e permessi.'],
        'user' => ['name' => 'Utente', 'description' => 'Ruolo predefinito, senza permessi di amministrazione.'],
    ],
    'areas' => [
        'users' => 'Utenti',
        'roles' => 'Ruoli',
        'permissions' => 'Permessi',
        'settings' => 'Impostazioni',
        'sessions' => 'Sessioni',
        'activity' => 'Attività',
        'security' => 'Sicurezza',
        'appearance' => 'Aspetto',
    ],
    'permissions' => [
        'users' => [
            'view' => 'Vedere gli utenti',
            'create' => 'Creare utenti',
            'update' => 'Modificare gli utenti',
            'delete' => 'Eliminare utenti',
            'activate' => 'Attivare e disattivare utenti',
            'import' => 'Importare utenti da un file CSV',
        ],
        'roles' => [
            'view' => 'Vedere i ruoli',
            'create' => 'Creare ruoli',
            'update' => 'Modificare i ruoli',
            'delete' => 'Eliminare ruoli',
            'assign' => 'Assegnare ruoli agli utenti',
        ],
        'permissions' => [
            'view' => 'Vedere i permessi',
            'override' => 'Concedere o negare un permesso a un singolo utente',
        ],
        'settings' => [
            'view' => 'Vedere le impostazioni del modulo',
            'update' => 'Cambiare le impostazioni del modulo',
        ],
        'sessions' => [
            'view' => 'Vedere le sessioni aperte degli altri utenti',
            'revoke' => 'Chiudere le sessioni degli altri utenti',
        ],
        'activity' => [
            'view' => 'Leggere il log attività',
        ],
        'security' => [
            'alerts' => 'Ricevere le email di allarme sicurezza (troppi accessi falliti)',
        ],
        'appearance' => [
            'update' => 'Cambiare il tema del pannello',
        ],
    ],
];
