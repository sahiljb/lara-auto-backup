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

];
