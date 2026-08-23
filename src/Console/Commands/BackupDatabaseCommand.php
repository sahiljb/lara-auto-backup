<?php

namespace SahilJB\LaraAutoBackup\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SahilJB\LaraAutoBackup\DatabaseDumper;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
        {--disk= : Filesystem disk to upload to (overrides config)}
        {--connection= : Database connection to back up (overrides config)}
        {--no-compress : Upload an uncompressed .sql dump}
        {--keep-days= : Days of backups to retain on the disk}';

    protected $description = 'Dump the database and upload it to S3 / Cloudflare R2.';

    public function handle(): int
    {
        $disk = $this->option('disk') ?: config('auto-backup.disk');
        $connection = $this->option('connection') ?: config('auto-backup.connection') ?: config('database.default');
        $compress = $this->option('no-compress') ? false : (bool) config('auto-backup.compress');
        $keepDays = (int) ($this->option('keep-days') ?? config('auto-backup.keep_days'));

        $dbConfig = config("database.connections.{$connection}");

        if (! $dbConfig) {
            $this->error("Database connection [{$connection}] is not configured.");

            return self::FAILURE;
        }

        $tempPath = rtrim(config('auto-backup.temp_path'), '/');

        if (! is_dir($tempPath) && ! mkdir($tempPath, 0700, true) && ! is_dir($tempPath)) {
            $this->error("Unable to create temp directory [{$tempPath}].");

            return self::FAILURE;
        }

        $filename = sprintf('%s-%s.sql', $dbConfig['database'] ?: $connection, now()->format('Y-m-d-His'));
        $localPath = $tempPath . '/' . $filename;

        try {
            $this->info("Dumping [{$connection}]...");

            $dumpPath = (new DatabaseDumper($dbConfig, config('auto-backup.dump_binary_path')))
                ->dumpTo($localPath, $compress);

            $remotePath = trim(config('auto-backup.path'), '/') . '/' . basename($dumpPath);

            $this->info(sprintf('Uploading %s (%s) to [%s]...', basename($dumpPath), $this->humanSize(filesize($dumpPath)), $disk));

            $stream = fopen($dumpPath, 'rb');

            try {
                Storage::disk($disk)->writeStream($remotePath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (config('auto-backup.delete_local_after_upload')) {
                @unlink($dumpPath);
            }

            $this->info("Backup uploaded to {$remotePath}");

            if ($keepDays > 0) {
                $this->prune($disk, $keepDays);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            @unlink($localPath);
            @unlink($localPath . '.gz');

            $this->error('Backup failed: ' . $e->getMessage());
            report($e);
            $this->notifyFailure($e);

            return self::FAILURE;
        }
    }

    private function prune(string $disk, int $keepDays): void
    {
        $directory = trim(config('auto-backup.path'), '/');
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $storage = Storage::disk($disk);
        $deleted = 0;

        foreach ($storage->files($directory) as $file) {
            if ($storage->lastModified($file) < $cutoff) {
                $storage->delete($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("Pruned {$deleted} backup(s) older than {$keepDays} day(s).");
        }
    }

    private function notifyFailure(Throwable $e): void
    {
        $url = config('auto-backup.failure_webhook_url');

        if (! $url) {
            return;
        }

        try {
            Http::timeout(10)->post($url, [
                'text' => sprintf('[%s] Database backup failed: %s', config('app.name'), $e->getMessage()),
            ]);
        } catch (Throwable) {
            // A failing webhook must not mask the original backup failure.
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
