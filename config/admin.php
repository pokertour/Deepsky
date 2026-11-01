<?php

return [
    /*
     | Fuseau dans lequel l'admin saisit et lit les heures (publication programmée,
     | journal de connexion) ; l'application stocke tout en UTC.
     */
    'timezone' => env('ADMIN_TIMEZONE', 'Europe/Paris'),

    'emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    /*
     | Historique des tâches (admin > Tâches) : commandes Artisan suivies, par
     | préfixe de nom, et durée de conservation avant purge par model:prune.
     */
    'task_history' => [
        'tracked_commands' => ['astrophotos:', 'nightscope:', 'newsletter:', 'r2:', 'model:prune', 'maintenance:', 'queue:prune-failed'],
        'retention_days' => 30,
    ],
];
