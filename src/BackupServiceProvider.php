<?php

namespace SahilJB\LaraAutoBackup;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SahilJB\LaraAutoBackup\Console\Commands\BackupDatabaseCommand;
use SahilJB\LaraAutoBackup\Console\Commands\CleanBackupsCommand;
use SahilJB\LaraAutoBackup\Console\Commands\ListBackupsCommand;
use SahilJB\LaraAutoBackup\Dumpers\DumperFactory;
use SahilJB\LaraAutoBackup\Events\BackupCompleted;
use SahilJB\LaraAutoBackup\Events\BackupFailed;
use SahilJB\LaraAutoBackup\Listeners\SendWebhookNotification;

class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/auto-backup.php', 'auto-backup');

        $this->app->singleton(DumperFactory::class, fn ($app) => new DumperFactory(
            $app,
            $app['config']->get('auto-backup.dumpers', [])
        ));

        $this->app->singleton(BackupManager::class, fn ($app) => new BackupManager(
            $app->make(DumperFactory::class),
            $app->make(FilesystemFactory::class),
            $app['config']->get('auto-backup', [])
        ));

        $this->app->alias(BackupManager::class, 'auto-backup');
    }

    public function boot(): void
    {
        $this->registerListeners();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/auto-backup.php' => config_path('auto-backup.php'),
            ], 'auto-backup-config');

            $this->commands([
                BackupDatabaseCommand::class,
                ListBackupsCommand::class,
                CleanBackupsCommand::class,
            ]);

            $this->registerSchedule();
        }
    }

    private function registerListeners(): void
    {
        Event::listen(BackupFailed::class, [SendWebhookNotification::class, 'handleFailure']);
        Event::listen(BackupCompleted::class, [SendWebhookNotification::class, 'handleSuccess']);
    }

    private function registerSchedule(): void
    {
        if (! $this->app['config']->get('auto-backup.schedule.enabled', true)) {
            return;
        }

        // Deferred until the app has booted so the scheduler and the published
        // config are both fully available.
        $this->app->booted(function () {
            $schedule = $this->app['config']->get('auto-backup.schedule', []);

            $event = $this->app->make(Schedule::class)
                ->command(BackupDatabaseCommand::class)
                ->cron($schedule['cron'] ?? '0 * * * *');

            if (! empty($schedule['timezone'])) {
                $event->timezone($schedule['timezone']);
            }

            if ($schedule['without_overlapping'] ?? true) {
                $event->withoutOverlapping();
            }

            if ($schedule['on_one_server'] ?? false) {
                $event->onOneServer();
            }

            if ($schedule['in_background'] ?? false) {
                $event->runInBackground();
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [BackupManager::class, DumperFactory::class, 'auto-backup'];
    }
}
