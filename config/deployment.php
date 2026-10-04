<?php

return [
    'dispatch_worker_confirmed' => env('ERP_DISPATCH_WORKER_CONFIRMED', false),
    'backup_enabled' => env('ERP_BACKUP_ENABLED', false),
    'backup_root' => storage_path('app/private/erp-backups'),
    'backup_password' => env('ERP_BACKUP_PASSWORD'),
    'backup_disk' => env('ERP_BACKUP_OFFSITE_DISK'),
    'backup_retention_days' => 14,
    'backup_max_age_hours' => 26,
    'upload_roots' => [public_path('uploads'), storage_path('app/public'), storage_path('app/documents')],
    'mysql_dump_binary' => env('ERP_MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('ERP_MYSQL_BINARY', 'mysql'),
    'restore_root' => storage_path('app/private/restore-rehearsals'),
    'restore_mysql' => [
        'driver' => 'mysql', 'host' => env('ERP_RESTORE_DB_HOST', '127.0.0.1'), 'port' => env('ERP_RESTORE_DB_PORT', 3306),
        'database' => env('ERP_RESTORE_DB_DATABASE'), 'username' => env('ERP_RESTORE_DB_USERNAME'),
        'password' => env('ERP_RESTORE_DB_PASSWORD'), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
    ],
];
