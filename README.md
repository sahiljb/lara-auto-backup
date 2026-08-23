# lara-auto-backup

Automated database backups for Laravel: dumps MySQL / MariaDB / PostgreSQL / SQLite, gzips the dump, streams it to **AWS S3** or **Cloudflare R2**, and prunes old backups.

## Install

```bash
composer require sahiljb/lara-auto-backup
php artisan vendor:publish --tag=auto-backup-config
```

## Configure the destination disk

Both S3 and R2 use the `s3` driver. Add a disk in `config/filesystems.php`:

**AWS S3**

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```

**Cloudflare R2**

```php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'), // https://<account-id>.r2.cloudflarestorage.com
    'use_path_style_endpoint' => true,
],
```

Then point the package at it:

```dotenv
BACKUP_DISK=r2
BACKUP_PATH=backups
BACKUP_KEEP_DAYS=14
BACKUP_COMPRESS=true
```

## Run

```bash
php artisan backup:database
php artisan backup:database --disk=s3 --connection=mysql --keep-days=30
php artisan backup:database --no-compress
```

## Schedule it

In `routes/console.php` (Laravel 11+) or `app/Console/Kernel.php`:

```php
Schedule::command('backup:database')->dailyAt('02:00')->onOneServer();
```

## Config reference

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `disk` | `BACKUP_DISK` | `s3` | Destination filesystem disk |
| `path` | `BACKUP_PATH` | `backups` | Folder inside the bucket |
| `connection` | `BACKUP_DB_CONNECTION` | default connection | Which database to dump |
| `compress` | `BACKUP_COMPRESS` | `true` | gzip the dump before upload |
| `keep_days` | `BACKUP_KEEP_DAYS` | `14` | Prune remote backups older than this (`0` disables) |
| `dump_binary_path` | `BACKUP_DUMP_BINARY_PATH` | `null` | Directory holding `mysqldump` / `pg_dump` |
| `failure_webhook_url` | `BACKUP_FAILURE_WEBHOOK_URL` | `null` | POSTed a JSON `{"text": ...}` on failure |

## Notes

- `mysqldump` / `pg_dump` must be installed on the machine running the command.
- Database passwords are passed via `MYSQL_PWD` / `PGPASSWORD`, so they never appear in the process list.
- The dump is streamed to the disk, so large databases don't get loaded into memory.
- Restore: `gunzip < backup.sql.gz | mysql -u user -p dbname`
