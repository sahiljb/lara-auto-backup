<?php

namespace SahilJB\LaraAutoBackup\Console\Commands;

use Illuminate\Console\Command;
use SahilJB\LaraAutoBackup\BackupManager;
use SahilJB\LaraAutoBackup\Support\FileSize;

class ListBackupsCommand extends Command
{
    protected $signature = 'backup:list {--disk= : Disk to list (defaults to the first configured disk)}';

    protected $description = 'List the database backups stored on a disk.';

    public function handle(BackupManager $backup): int
    {
        $backups = $backup->list($this->option('disk'));

        if ($backups === []) {
            $this->components->warn('No backups found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Archive', 'Size', 'Created'],
            array_map(fn (array $file) => [
                $file['path'],
                FileSize::human($file['size']),
                date('Y-m-d H:i:s', $file['last_modified']),
            ], $backups)
        );

        $this->components->info(sprintf(
            '%d backup(s), %s total.',
            count($backups),
            FileSize::human((int) array_sum(array_column($backups, 'size')))
        ));

        return self::SUCCESS;
    }
}
