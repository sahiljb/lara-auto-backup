<?php

namespace SahilJB\LaraAutoBackup\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use SahilJB\LaraAutoBackup\BackupManager;
use SahilJB\LaraAutoBackup\Events\RestoreCompleted;
use SahilJB\LaraAutoBackup\Events\RestoreFailed as RestoreFailedEvent;
use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;

class RestoreTest extends TestCase
{
    public function test_it_restores_a_database_from_an_archive(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $manager = $this->app->make(BackupManager::class);
        $archive = $manager->run()[0]->remotePath;

        // Lose the data the backup was taken of.
        $this->connection()->table('posts')->delete();
        $this->assertSame(0, $this->connection()->table('posts')->count());

        $manager->restore($archive);

        $this->assertSame(2, $this->connection()->table('posts')->count());
        $this->assertSame('first', $this->connection()->table('posts')->first()->title);
    }

    public function test_it_restores_an_uncompressed_archive(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $manager = $this->app->make(BackupManager::class);
        $archive = $manager->run(['compress' => false])[0]->remotePath;

        $this->connection()->table('posts')->delete();

        $manager->restore($archive);

        $this->assertSame(2, $this->connection()->table('posts')->count());
    }

    public function test_it_dispatches_a_completed_event(): void
    {
        Storage::fake('backups');
        Event::fake([RestoreCompleted::class]);
        $this->seedDatabase();

        $manager = $this->app->make(BackupManager::class);
        $archive = $manager->run()[0]->remotePath;

        $manager->restore($archive);

        Event::assertDispatched(RestoreCompleted::class);
    }

    public function test_it_fails_when_the_archive_is_missing(): void
    {
        Storage::fake('backups');
        Event::fake([RestoreFailedEvent::class]);

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageMatches('/was not found on disk/');

        try {
            $this->app->make(BackupManager::class)->restore('backups/nope.sqlite.gz');
        } finally {
            Event::assertDispatched(RestoreFailedEvent::class);
        }
    }

    public function test_it_leaves_the_database_intact_when_the_archive_is_corrupt(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        Storage::disk('backups')->put('backups/corrupt.sqlite', 'this is not a database');

        $this->app->make(BackupManager::class)->restore('backups/corrupt.sqlite');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->connection()->table('posts')->count();
    }

    public function test_the_restore_command_restores_the_latest_archive(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $this->artisan('backup:database')->assertSuccessful();
        $this->connection()->table('posts')->delete();

        $this->artisan('backup:restore --latest --force')->assertSuccessful();

        $this->assertSame(2, $this->connection()->table('posts')->count());
    }

    public function test_the_restore_command_fails_when_nothing_is_stored(): void
    {
        Storage::fake('backups');

        $this->artisan('backup:restore --latest --force')
            ->expectsOutputToContain('No backups found to restore.')
            ->assertFailed();
    }

    private function connection(): \Illuminate\Database\Connection
    {
        return $this->app['db']->connection('backup_testing');
    }
}
