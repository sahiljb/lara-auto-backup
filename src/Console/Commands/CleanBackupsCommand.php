<?php

namespace SahilJB\LaraAutoBackup\Console\Commands;

use Illuminate\Console\Command;
use SahilJB\LaraAutoBackup\BackupManager;

class CleanBackupsCommand extends Command
{
    protected $signature = 'backup:clean
        {--disk=* : Disk(s) to prune, overriding config}
        {--days= : Retention window in days, overriding config}
        {--keep-at-least= : Always keep this many of the newest archives}';

    protected $description = 'Delete backups that fall outside the retention window.';

    public function handle(BackupManager $backup): int
    {
        $backup->onProgress(fn (string $message) => $this->line("  {$message}"));

        $overrides = ['disks' => $this->option('disk')];
        $retention = [];

        if ($this->option('days') !== null) {
            $retention['days'] = (int) $this->option('days');
        }

        if ($this->option('keep-at-least') !== null) {
            $retention['keep_at_least'] = (int) $this->option('keep-at-least');
        }

        if ($retention !== []) {
            $overrides['retention'] = $retention;
        }

        $deleted = $backup->prune($overrides);

        $this->components->info($deleted === 0 ? 'Nothing to prune.' : "Deleted {$deleted} backup(s).");

        return self::SUCCESS;
    }
}
