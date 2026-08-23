<?php

return [

    // Filesystem disk (configured in config/filesystems.php) that backups are uploaded to.
    // Point this at an S3 or Cloudflare R2 disk — see README for sample disk config.
    'disk' => env('BACKUP_DISK', 's3'),

    // Folder inside the disk where backup archives are stored.
    'path' => env('BACKUP_PATH', 'backups'),

    // Database connection to back up (null = default connection from config/database.php).
    'connection' => env('BACKUP_DB_CONNECTION'),

    // Compress the dump with gzip before uploading.
    'compress' => env('BACKUP_COMPRESS', true),

    // Delete local temp dump file after upload.
    'delete_local_after_upload' => true,

    // Directory used to stage the dump file before upload.
    'temp_path' => storage_path('app/backup-tmp'),

    // How many days of backups to keep on the remote disk. Set to 0 to disable pruning.
    'keep_days' => env('BACKUP_KEEP_DAYS', 14),

    // Path to the mysqldump / pg_dump binary. Leave null to use the one on PATH.
    'dump_binary_path' => env('BACKUP_DUMP_BINARY_PATH'),

    // Notification webhook (e.g. Slack incoming webhook) triggered on backup failure. Optional.
    'failure_webhook_url' => env('BACKUP_FAILURE_WEBHOOK_URL'),

    // Automatic scheduling. The package registers the command with Laravel's
    // scheduler itself, so you only need `php artisan schedule:work` (or the
    // system cron entry) running — no edits to routes/console.php required.
    'schedule' => [

        'enabled' => env('BACKUP_SCHEDULE_ENABLED', true),

        // Any valid cron expression. Examples:
        //   '0 * * * *'    hourly, on the hour  (default)
        //   '*/30 * * * *' every 30 minutes
        //   '0 */6 * * *'  every 6 hours
        //   '0 2 * * *'    daily at 02:00
        'cron' => env('BACKUP_SCHEDULE_CRON', '0 * * * *'),

        // Timezone the cron expression is evaluated in (null = app timezone).
        'timezone' => env('BACKUP_SCHEDULE_TIMEZONE'),

        // Skip a run if the previous one is still going — important for hourly
        // backups of a large database.
        'without_overlapping' => true,

        // Run on a single server only when using multiple app servers.
        // Requires a cache driver that supports locks (redis, memcached, database).
        'on_one_server' => env('BACKUP_SCHEDULE_ON_ONE_SERVER', false),

    ],

];
