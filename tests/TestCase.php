<?php

namespace SahilJB\LaraAutoBackup\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SahilJB\LaraAutoBackup\BackupServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = $this->app->basePath('database/backup-test.sqlite');
    }

    protected function tearDown(): void
    {
        @unlink($this->databasePath);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [BackupServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $path = $app->basePath('database/backup-test.sqlite');

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, '');

        $app['config']->set('database.default', 'backup_testing');
        $app['config']->set('database.connections.backup_testing', [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
        ]);

        $app['config']->set('auto-backup.disks', ['backups']);
        $app['config']->set('auto-backup.connections', []);
        $app['config']->set('auto-backup.temp_path', $app->basePath('storage/backup-tmp'));
    }

    /**
     * Create the test SQLite database and give it something to back up.
     */
    protected function seedDatabase(): void
    {
        $this->app['db']->connection('backup_testing')->statement(
            'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY, title TEXT)'
        );

        $this->app['db']->connection('backup_testing')->table('posts')->insert([
            ['title' => 'first'],
            ['title' => 'second'],
        ]);
    }
}
