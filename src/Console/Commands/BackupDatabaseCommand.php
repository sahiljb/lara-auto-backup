<?php

namespace SahilJB\LaraAutoBackup\Console\Commands;

use Illuminate\Console\Command;
use SahilJB\LaraAutoBackup\BackupManager;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
        {--disk=* : Disk(s) to upload to, overriding config}
        {--connection=* : Database connection(s) to back up, overriding config}
        {--path= : Folder inside the disk to upload to}
        {--no-compress : Upload an uncompressed dump}
        {--keep-days= : Retention window in days (0 disables pruning)}
        {--only-table=* : Back up only these tables}
        {--exclude-table=* : Skip these tables}';

    protected $description = 'Back up the database and upload it to S3 / Cloudflare R2.';

    public function handle(BackupManager $backup): int
    {
        $backup->onProgress(fn (string $message) => $this->line("  {$message}"));

        try {
            $results = $backup->run($this->overrides());
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            report($e);

            return self::FAILURE;
        }

        foreach ($results as $result) {
            $this->components->info(sprintf(
                '%s → %s (%s) in %ss',
                $result->database,
                $result->remotePath,
                $result->humanSize(),
                $result->duration
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        $overrides = [
            'disks' => $this->option('disk'),
            'connections' => $this->option('connection'),
            'path' => $this->option('path'),
            'only_tables' => $this->option('only-table'),
            'exclude_tables' => $this->option('exclude-table'),
        ];

        if ($this->option('no-compress')) {
            $overrides['compress'] = false;
        }

        if ($this->option('keep-days') !== null) {
            $overrides['retention'] = ['days' => (int) $this->option('keep-days')];
        }

        return $overrides;
    }
}
