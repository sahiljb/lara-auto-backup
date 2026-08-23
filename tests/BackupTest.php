<?php

namespace SahilJB\LaraAutoBackup\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use SahilJB\LaraAutoBackup\BackupManager;
use SahilJB\LaraAutoBackup\Events\BackupCompleted;
use SahilJB\LaraAutoBackup\Events\BackupFailed as BackupFailedEvent;
use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;

class BackupTest extends TestCase
{
    public function test_it_dumps_the_database_and_uploads_it_to_the_disk(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $results = $this->app->make(BackupManager::class)->run();

        $this->assertCount(1, $results);
        $this->assertStringStartsWith('backups/', $results[0]->remotePath);
        $this->assertGreaterThan(0, $results[0]->size);

        Storage::disk('backups')->assertExists($results[0]->remotePath);
    }

    public function test_it_compresses_the_archive_by_default(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $result = $this->app->make(BackupManager::class)->run()[0];

        $this->assertStringEndsWith('.sqlite.gz', $result->filename);
    }

    public function test_it_can_upload_an_uncompressed_archive(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $result = $this->app->make(BackupManager::class)->run(['compress' => false])[0];

        $this->assertStringEndsWith('.sqlite', $result->filename);
        Storage::disk('backups')->assertExists($result->remotePath);
    }

    public function test_it_uploads_to_every_configured_disk(): void
    {
        Storage::fake('backups');
        Storage::fake('secondary');
        $this->seedDatabase();

        $result = $this->app->make(BackupManager::class)->run(['disks' => ['backups', 'secondary']])[0];

        Storage::disk('backups')->assertExists($result->remotePath);
        Storage::disk('secondary')->assertExists($result->remotePath);
    }

    public function test_the_filename_format_is_configurable(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $result = $this->app->make(BackupManager::class)->run([
            'filename_format' => 'nightly-{connection}',
            'compress' => false,
        ])[0];

        $this->assertSame('nightly-backup_testing.sqlite', $result->filename);
    }

    public function test_it_deletes_the_local_dump_after_uploading(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $result = $this->app->make(BackupManager::class)->run()[0];

        $this->assertFileDoesNotExist(
            config('auto-backup.temp_path') . DIRECTORY_SEPARATOR . $result->filename
        );
    }

    public function test_it_dispatches_a_completed_event(): void
    {
        Storage::fake('backups');
        Event::fake([BackupCompleted::class]);
        $this->seedDatabase();

        $this->app->make(BackupManager::class)->run();

        Event::assertDispatched(BackupCompleted::class);
    }

    public function test_it_dispatches_a_failed_event_and_throws_for_an_unknown_connection(): void
    {
        Event::fake([BackupFailedEvent::class]);

        $this->expectException(BackupFailed::class);

        try {
            $this->app->make(BackupManager::class)->run(['connections' => ['nope']]);
        } finally {
            Event::assertNotDispatched(BackupCompleted::class);
        }
    }

    public function test_it_throws_for_an_unsupported_driver(): void
    {
        config()->set('database.connections.mongo', ['driver' => 'mongo', 'database' => 'x']);

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageMatches('/No dumper registered/');

        $this->app->make(BackupManager::class)->run(['connections' => ['mongo']]);
    }
}
