<?php

namespace SahilJB\LaraAutoBackup\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use SahilJB\LaraAutoBackup\BackupManager;
use SahilJB\LaraAutoBackup\Support\FileSize;
use Throwable;

class RestoreDatabaseCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'backup:restore
        {archive? : Path of the archive on the disk. Omit to choose one}
        {--disk= : Disk to restore from (defaults to the first configured disk)}
        {--connection= : Database connection to restore into}
        {--latest : Restore the most recent archive without prompting}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Restore a database from a backup stored on S3 / Cloudflare R2.';

    public function handle(BackupManager $backup): int
    {
        $disk = $this->option('disk');
        $connection = $this->option('connection') ?: config('auto-backup.connections.0') ?: config('database.default');

        try {
            $archive = $this->resolveArchive($backup, $disk);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($archive === null) {
            $this->components->warn('No backups found to restore.');

            return self::FAILURE;
        }

        $database = config("database.connections.{$connection}.database");

        $this->components->warn("This will overwrite the [{$connection}] database ({$database}) with {$archive}.");

        // Prompts here, and refuses outright in production without --force.
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $backup->onProgress(fn (string $message) => $this->line("  {$message}"));

        try {
            $backup->restore($archive, array_filter([
                'disk' => $disk,
                'connection' => $connection,
            ]));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->components->info("Restored [{$connection}] from {$archive}.");

        return self::SUCCESS;
    }

    /**
     * Work out which archive to restore: the argument, the newest one, or a
     * choice from the archives on the disk.
     */
    private function resolveArchive(BackupManager $backup, ?string $disk): ?string
    {
        if ($archive = $this->argument('archive')) {
            return $archive;
        }

        $archives = $backup->list($disk);

        if ($archives === []) {
            return null;
        }

        if ($this->option('latest') || ! $this->input->isInteractive()) {
            return $archives[0]['path'];
        }

        $choices = [];

        foreach ($archives as $file) {
            $choices[$file['path']] = sprintf(
                '%s — %s, %s',
                $file['path'],
                FileSize::human($file['size']),
                date('Y-m-d H:i:s', $file['last_modified'])
            );
        }

        return $this->choice('Which backup would you like to restore?', $choices, array_key_first($choices));
    }
}
