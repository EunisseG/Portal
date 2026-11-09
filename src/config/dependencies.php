<?php

/*
| Actualización de dependencias desde el panel (Sistema → Dependencias /
| Seguridad). Cada actualización hace antes un respaldo completo (base de
| datos, vendor, lockfiles y assets compilados) en este mismo servidor.
*/

return [
    // Carpeta privada (no pública) donde se guardan los respaldos.
    'backup_path' => env('DEPENDENCY_BACKUP_PATH', storage_path('app/dependency-backups')),

    // Respaldos que se conservan; los más antiguos se borran solos.
    'keep_backups' => (int) env('DEPENDENCY_KEEP_BACKUPS', 3),

    // Espacio libre extra (bytes) que se exige además del tamaño estimado.
    'min_free_margin' => 512 * 1024 * 1024,

    // Tiempo máximo (segundos) de toda la operación en la cola.
    'job_timeout' => 1800,

    // Tablas que NO se respaldan ni se restauran: el historial de esta misma
    // función (para no perderlo al restaurar), colas, caché y sesiones.
    'excluded_tables' => [
        'dependency_updates',
        'dependency_audits',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
    ],
];
