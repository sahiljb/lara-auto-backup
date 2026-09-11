# Lara Auto Backup

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sahiljb/lara-auto-backup.svg?style=flat-square)](https://packagist.org/packages/sahiljb/lara-auto-backup)
[![Tests](https://img.shields.io/github/actions/workflow/status/sahiljb/lara-auto-backup/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sahiljb/lara-auto-backup/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/sahiljb/lara-auto-backup.svg?style=flat-square)](https://packagist.org/packages/sahiljb/lara-auto-backup)
[![License](https://img.shields.io/packagist/l/sahiljb/lara-auto-backup.svg?style=flat-square)](LICENSE)

Automated database backups for Laravel. Dumps your database on a schedule,
compresses it, and streams it straight to **AWS S3** or **Cloudflare R2** — then
prunes the old archives so the bucket doesn't grow forever.

```bash
php artisan backup:database
```

```
  Dumping [mysql]…
  Uploading shop-2026-08-23-140000.sql.gz to [r2]…
  INFO  shop → backups/shop-2026-08-23-140000.sql.gz (14.2 MB) in 3.81s
```

## Features

- **Runs itself** — registers with Laravel's scheduler, hourly by default, and the frequency is a `.env` value.
- **S3 and Cloudflare R2** — anything with an S3-compatible driver. Upload to several disks at once for an off-provider copy.
- **MySQL, MariaDB, PostgreSQL, SQLite** — and the dumpers are swappable if you need something else.
- **Memory-safe** — the archive is streamed to the disk, so database size doesn't matter.
- **Retention** — prune by age, with a `keep_at_least` safety net so a paused scheduler can't leave you with an empty bucket.
- **One-command restore** — `backup:restore` pulls an archive back out of the bucket, with a confirmation prompt and production guard.
- **Events + webhooks** — hook into success and failure, or POST to Slack/Discord.
- **No credentials in the process list** — passwords go through `MYSQL_PWD` / `PGPASSWORD`.

## Requirements

- PHP 8.1+
- Laravel 10, 11 or 12
- `mysqldump` or `pg_dump` on the server (SQLite needs neither)
- `league/flysystem-aws-s3-v3` ^3.0 for S3/R2 uploads

## Installation

```bash
composer require sahiljb/lara-auto-backup
```

The service provider is auto-discovered. Publish the config file:

```bash
php artisan vendor:publish --tag=auto-backup-config
```

If your app doesn't already have the S3 adapter:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

## Configuring the destination

Both S3 and R2 use Laravel's `s3` driver — R2 just needs an endpoint. Add a disk
to `config/filesystems.php`:

### AWS S3

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```

### Cloudflare R2

```php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => true,
],
```

```dotenv
R2_ACCESS_KEY_ID=xxxxxxxx
R2_SECRET_ACCESS_KEY=xxxxxxxx
R2_BUCKET=my-backups
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
```

Then point the package at the disk:

```dotenv
BACKUP_DISKS=r2
```

Want a copy in both places? List them:

```dotenv
BACKUP_DISKS=r2,s3
```

## Scheduling

The package **registers itself with Laravel's scheduler**, so there is nothing to
add to `routes/console.php`. You only need Laravel's usual single cron entry:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

It backs up hourly out of the box. Change the frequency from `.env` at any time —
no code change, no redeploy:

```dotenv
BACKUP_SCHEDULE_CRON="0 * * * *"     # hourly (default)
BACKUP_SCHEDULE_CRON="*/30 * * * *"  # every 30 minutes
BACKUP_SCHEDULE_CRON="0 */6 * * *"   # every 6 hours
BACKUP_SCHEDULE_CRON="0 2 * * *"     # daily at 02:00
BACKUP_SCHEDULE_CRON="0 2 * * 0"     # weekly, Sunday 02:00
```

Check what's registered with `php artisan schedule:list`.

Prefer to wire it up yourself? Turn the built-in schedule off and schedule the
command by hand:

```dotenv
BACKUP_SCHEDULE_ENABLED=false
```

```php
Schedule::command('backup:database')->hourly()->withoutOverlapping()->onOneServer();
```

### Notes for frequent backups

- Overlap protection is on by default, so a slow dump never stacks on the next run.
- Retention is measured in **days**: hourly backups with `BACKUP_KEEP_DAYS=14`
  leaves ~336 archives. Lower it, or use a bucket lifecycle rule, if that's more
  than you want to store.
- On multiple app servers set `BACKUP_SCHEDULE_ON_ONE_SERVER=true` (needs a
  lock-capable cache driver: redis, memcached or database).
- `BACKUP_SCHEDULE_IN_BACKGROUND=true` lets `schedule:run` return immediately
  instead of waiting for a long dump.

## Commands

```bash
# Back up everything configured
php artisan backup:database

# One-off overrides
php artisan backup:database --disk=s3 --connection=mysql
php artisan backup:database --disk=r2 --disk=s3          # several disks
php artisan backup:database --no-compress
php artisan backup:database --keep-days=30
php artisan backup:database --exclude-table=sessions --exclude-table=jobs
php artisan backup:database --only-table=users --only-table=orders

# See what's stored
php artisan backup:list
php artisan backup:list --disk=s3

# Prune old archives
php artisan backup:clean --days=7
php artisan backup:clean --days=7 --keep-at-least=0

# Restore (see Restoring below)
php artisan backup:restore
php artisan backup:restore --latest --force
```

## Configuration

Everything below lives in `config/auto-backup.php` and is env-overridable.

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `disks` | `BACKUP_DISKS` | `s3` | Destination disk(s), comma-separated |
| `path` | `BACKUP_PATH` | `backups` | Folder inside the bucket |
| `connections` | `BACKUP_DB_CONNECTIONS` | default connection | Connection(s) to back up, comma-separated |
| `filename_format` | `BACKUP_FILENAME_FORMAT` | `{database}-{timestamp}` | Archive name; tokens `{app}` `{database}` `{connection}` `{timestamp}` |
| `timestamp_format` | `BACKUP_TIMESTAMP_FORMAT` | `Y-m-d-His` | PHP date format for `{timestamp}` |
| `compress` | `BACKUP_COMPRESS` | `true` | gzip before upload |
| `exclude_tables` | `BACKUP_EXCLUDE_TABLES` | — | Tables to skip, comma-separated |
| `only_tables` | `BACKUP_ONLY_TABLES` | — | Back up only these tables |
| `dump_options` | — | `[]` | Extra flags for the dump binary |
| `temp_path` | — | `storage/app/backup-tmp` | Local staging directory |
| `timeout` | `BACKUP_TIMEOUT` | `3600` | Seconds the dump may run |
| `dump_binary_path` | `BACKUP_DUMP_BINARY_PATH` | — | Directory holding `mysqldump` / `pg_dump` |
| `retention.days` | `BACKUP_KEEP_DAYS` | `14` | Prune archives older than this (`0` disables) |
| `retention.keep_at_least` | `BACKUP_KEEP_AT_LEAST` | `3` | Never prune below this many archives |
| `dumpers` | — | see config | Driver → dumper class map |
| `restorers` | — | see config | Driver → restorer class map |
| `notifications.webhook_url` | `BACKUP_WEBHOOK_URL` | — | POSTed `{"text": "..."}` |
| `notifications.on_failure` | `BACKUP_NOTIFY_ON_FAILURE` | `true` | Notify when a backup fails |
| `notifications.on_success` | `BACKUP_NOTIFY_ON_SUCCESS` | `false` | Notify on every success |
| `schedule.enabled` | `BACKUP_SCHEDULE_ENABLED` | `true` | Let the package schedule itself |
| `schedule.cron` | `BACKUP_SCHEDULE_CRON` | `0 * * * *` | How often the backup runs |
| `schedule.timezone` | `BACKUP_SCHEDULE_TIMEZONE` | app timezone | Timezone for the cron expression |
| `schedule.without_overlapping` | `BACKUP_SCHEDULE_WITHOUT_OVERLAPPING` | `true` | Skip a run while one is in progress |
| `schedule.on_one_server` | `BACKUP_SCHEDULE_ON_ONE_SERVER` | `false` | Only one server runs it |
| `schedule.in_background` | `BACKUP_SCHEDULE_IN_BACKGROUND` | `false` | Don't block `schedule:run` |

## Programmatic use

Use the facade anywhere — a controller, a job, a custom command:

```php
use SahilJB\LaraAutoBackup\Facades\Backup;

$results = Backup::run();

foreach ($results as $result) {
    logger()->info("Backed up {$result->database} ({$result->humanSize()}) to {$result->remotePath}");
}

// A single connection, with overrides
$result = Backup::backupConnection('tenant_db', [
    'disks' => ['r2'],
    'path' => 'tenants/acme',
    'exclude_tables' => ['sessions'],
]);

// Housekeeping
Backup::prune(['retention' => ['days' => 7]]);
$archives = Backup::list('r2');
```

`BackupResult` exposes `connection`, `database`, `filename`, `remotePath`,
`size`, `disks`, `duration`, plus `humanSize()` and `toArray()`.

### Multi-tenant example

```php
Tenant::each(function (Tenant $tenant) {
    config()->set('database.connections.tenant', $tenant->databaseConfig());

    Backup::backupConnection('tenant', [
        'path' => "tenants/{$tenant->slug}",
    ]);
});
```

## Events

Listen for these to plug in your own notifications, metrics or audit records:

| Event | Fired when | Payload |
| --- | --- | --- |
| `SahilJB\LaraAutoBackup\Events\BackupStarted` | A connection's dump begins | `connection`, `database` |
| `SahilJB\LaraAutoBackup\Events\BackupCompleted` | Upload succeeded | `result` (`BackupResult`) |
| `SahilJB\LaraAutoBackup\Events\BackupFailed` | Anything threw | `connection`, `exception` |
| `SahilJB\LaraAutoBackup\Events\RestoreStarted` | A restore begins | `connection`, `database`, `archive` |
| `SahilJB\LaraAutoBackup\Events\RestoreCompleted` | Restore succeeded | `connection`, `database`, `archive`, `duration` |
| `SahilJB\LaraAutoBackup\Events\RestoreFailed` | A restore threw | `connection`, `archive`, `exception` |

```php
use Illuminate\Support\Facades\Event;
use SahilJB\LaraAutoBackup\Events\BackupFailed;

Event::listen(function (BackupFailed $event) {
    Notification::route('mail', 'ops@example.com')
        ->notify(new BackupBrokeNotification($event->exception));
});
```

## Webhook notifications

For a quick Slack/Discord/Teams ping without writing a listener:

```dotenv
BACKUP_WEBHOOK_URL=https://hooks.slack.com/services/xxx/yyy/zzz
BACKUP_NOTIFY_ON_FAILURE=true
BACKUP_NOTIFY_ON_SUCCESS=false
```

## Custom dumpers

Any driver can be handled by your own class — implement the `Dumper` contract
and map it in config:

```php
use SahilJB\LaraAutoBackup\Dumpers\Dumper;

class MongoDumper implements Dumper
{
    public function dump(array $connectionConfig, string $targetPath, array $options = []): void
    {
        // write the archive to $targetPath
    }

    public function extension(): string
    {
        return 'archive';
    }
}
```

```php
// config/auto-backup.php
'dumpers' => [
    'mongodb' => MongoDumper::class,
],
```

Or register one at runtime from a service provider:

```php
Backup::extend('mysql', fn (array $config) => new MyTunedMySqlDumper);
```

Restorers work the same way — implement `Restorers\Restorer`, then map it under
the `restorers` key or call `Backup::extendRestorer('mysql', ...)`.

## Restoring

`backup:restore` pulls the archive straight back out of R2/S3, decompresses it
and loads it into the database — you never touch the bucket by hand.

```bash
# Pick from a list of what's in the bucket
php artisan backup:restore

# Restore the most recent backup
php artisan backup:restore --latest

# Restore a specific archive
php artisan backup:restore backups/shop-2026-08-23-140000.sql.gz

# From a specific disk, into a specific connection
php artisan backup:restore --latest --disk=r2 --connection=mysql
```

Restoring **overwrites the target database**, so the command tells you what it's
about to replace and asks for confirmation. In `production` it refuses to run at
all without `--force`:

```bash
php artisan backup:restore --latest --force
```

Use `--force` in scripts and CI only when you're certain of the target — there
is no undo. A common safe pattern is restoring production data into a *staging*
connection:

```bash
php artisan backup:restore --latest --disk=r2 --connection=staging --force
```

Programmatically:

```php
use SahilJB\LaraAutoBackup\Facades\Backup;

Backup::restore('backups/shop-2026-08-23-140000.sql.gz', [
    'disk' => 'r2',
    'connection' => 'mysql',
]);
```

The archive is streamed to a temp file, loaded, then deleted, so restoring a
large database doesn't blow up memory. Afterwards the connection is purged so
your app reconnects to the restored data rather than the stale handle.

### Restoring by hand

If you'd rather download the archive from the R2 dashboard and do it yourself:

```bash
# MySQL / MariaDB
gunzip < shop-2026-08-23-140000.sql.gz | mysql -u user -p shop

# PostgreSQL
gunzip < shop-2026-08-23-140000.sql.gz | psql -U user -d shop

# SQLite (the archive is the database file)
gunzip < shop-2026-08-23-140000.sqlite.gz > database/database.sqlite
```

> **Test your restores.** A backup you have never restored is a hypothesis, not
> a backup. Restore into a scratch database periodically and confirm the data is
> what you expect.

## Testing

```bash
composer install
composer test
```

## Contributing

Pull requests are welcome. Please add tests for behaviour changes and keep the
suite green.

## Security

If you discover a security issue, email **sjbuddhadev@gmail.com** rather than
opening a public issue.

## Credits

- [Sahil JB](https://github.com/sahiljb)

## License

The MIT License. See [LICENSE](LICENSE) for details.
