<?php

use SahilJB\LaraAutoBackup\Dumpers\MySqlDumper;
use SahilJB\LaraAutoBackup\Dumpers\PostgresDumper;
use SahilJB\LaraAutoBackup\Dumpers\SqliteDumper;

return [

    /*
    |--------------------------------------------------------------------------
    | Destination disks
    |--------------------------------------------------------------------------
    |
    | One or more filesystem disks (from config/filesystems.php) that each
    | backup is uploaded to. Listing several disks writes the same archive to
    | all of them — e.g. ['r2', 's3'] to keep an off-provider copy.
    |
    */

    'disks' => array_filter(explode(',', (string) env('BACKUP_DISKS', env('BACKUP_DISK', 's3')))),

    // Folder inside each disk where backup archives are stored.
    'path' => env('BACKUP_PATH', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Databases
    |--------------------------------------------------------------------------
    |
    | Database connections to back up. Leave empty to use the application's
    | default connection, or list several to back them all up in one run.
    |
    */

    'connections' => array_filter(explode(',', (string) env('BACKUP_DB_CONNECTIONS', env('BACKUP_DB_CONNECTION', '')))),

    /*
    |--------------------------------------------------------------------------
    | Archive naming
    |--------------------------------------------------------------------------
    |
    | Available tokens: {app}, {database}, {connection}, {timestamp}.
    | The .sql / .sql.gz extension is appended automatically.
    |
    */

    'filename_format' => env('BACKUP_FILENAME_FORMAT', '{database}-{timestamp}'),

    'timestamp_format' => env('BACKUP_TIMESTAMP_FORMAT', 'Y-m-d-His'),

    // Compress the dump with gzip before uploading.
    'compress' => env('BACKUP_COMPRESS', true),

    /*
    |--------------------------------------------------------------------------
    | Table filtering
    |--------------------------------------------------------------------------
    |
    | Skip noisy tables ('exclude_tables'), or back up only a specific set
    | ('only_tables'). Leave both empty to dump the whole database.
    |
    */

    'exclude_tables' => array_filter(explode(',', (string) env('BACKUP_EXCLUDE_TABLES', ''))),

    'only_tables' => array_filter(explode(',', (string) env('BACKUP_ONLY_TABLES', ''))),

    // Extra flags appended verbatim to the dump command, e.g. ['--no-data'].
    'dump_options' => [],

    /*
    |--------------------------------------------------------------------------
    | Local staging
    |--------------------------------------------------------------------------
    */

    // Directory used to stage the dump file before upload.
    'temp_path' => storage_path('app/backup-tmp'),

    // Delete the local temp dump after a successful upload.
    'delete_local_after_upload' => true,

    // Seconds the dump process may run before being killed. null = no limit.
    'timeout' => env('BACKUP_TIMEOUT', 3600),

    // Directory holding the mysqldump / pg_dump binary. null = use PATH.
    'dump_binary_path' => env('BACKUP_DUMP_BINARY_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Old archives are pruned from every destination disk after each run.
    | 'keep_at_least' is a safety net: that many of the newest backups always
    | survive, however old they are.
    |
    */

    'retention' => [
        'days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'keep_at_least' => (int) env('BACKUP_KEEP_AT_LEAST', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dumpers
    |--------------------------------------------------------------------------
    |
    | Maps a database driver to the class that dumps it. Swap in your own
    | implementation of SahilJB\LaraAutoBackup\Dumpers\Dumper to customise how
    | a dump is produced, or to add support for another driver.
    |
    */

    'dumpers' => [
        'mysql' => MySqlDumper::class,
        'mariadb' => MySqlDumper::class,
        'pgsql' => PostgresDumper::class,
        'sqlite' => SqliteDumper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | A webhook (Slack, Discord, Teams, …) POSTed a JSON {"text": "..."} body.
    | For anything richer, listen for the package's events instead — see the
    | Events section of the README.
    |
    */

    'notifications' => [
        'webhook_url' => env('BACKUP_WEBHOOK_URL', env('BACKUP_FAILURE_WEBHOOK_URL')),
        'on_failure' => env('BACKUP_NOTIFY_ON_FAILURE', true),
        'on_success' => env('BACKUP_NOTIFY_ON_SUCCESS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | The package registers the backup command with Laravel's scheduler itself,
    | so the frequency is a config value rather than a code change. You only
    | need the usual `schedule:run` cron entry in place.
    |
    | See the README for cron examples (hourly, half-hourly, every 6 hours,
    | daily) — the default below runs the backup once an hour.
    |
    */

    'schedule' => [

        'enabled' => env('BACKUP_SCHEDULE_ENABLED', true),

        'cron' => env('BACKUP_SCHEDULE_CRON', '0 * * * *'),

        // Timezone the cron expression is evaluated in. null = app timezone.
        'timezone' => env('BACKUP_SCHEDULE_TIMEZONE'),

        // Skip a run while the previous one is still going.
        'without_overlapping' => env('BACKUP_SCHEDULE_WITHOUT_OVERLAPPING', true),

        // Run on one server only. Needs a lock-capable cache driver.
        'on_one_server' => env('BACKUP_SCHEDULE_ON_ONE_SERVER', false),

        // Run the dump in the background so schedule:run returns immediately.
        'in_background' => env('BACKUP_SCHEDULE_IN_BACKGROUND', false),

    ],

];
