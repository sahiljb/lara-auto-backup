<?php

namespace SahilJB\LaraAutoBackup;

use Illuminate\Console\Scheduling\Schedule;
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
            $this->registerSchedule();
        }
    }

    private function registerSchedule(): void
    {
        if (! config('auto-backup.schedule.enabled')) {
            return;
        }

        // Deferred until the scheduler is resolved so the config is fully loaded
        // and apps that don't use the scheduler pay nothing for this.
        $this->app->booted(function () {
            $event = $this->app->make(Schedule::class)
                ->command(BackupDatabaseCommand::class)
                ->cron(config('auto-backup.schedule.cron', '0 * * * *'));

            if ($timezone = config('auto-backup.schedule.timezone')) {
                $event->timezone($timezone);
            }

            if (config('auto-backup.schedule.without_overlapping', true)) {
                $event->withoutOverlapping();
            }

            if (config('auto-backup.schedule.on_one_server', false)) {
                $event->onOneServer();
            }
        });
    }
}
