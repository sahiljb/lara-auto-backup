<?php

namespace SahilJB\LaraAutoBackup;

use Illuminate\Support\ServiceProvider;
use SahilJB\LaraAutoBackup\Console\Commands\BackupDatabaseCommand;

class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/auto-backup.php', 'auto-backup');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/auto-backup.php' => config_path('auto-backup.php'),
            ], 'auto-backup-config');

            $this->commands([BackupDatabaseCommand::class]);
        }
    }
}
