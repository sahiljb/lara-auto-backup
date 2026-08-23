<?php

namespace SahilJB\LaraAutoBackup\Tests;

use Illuminate\Support\Facades\Storage;

class CommandsTest extends TestCase
{
    public function test_the_backup_command_uploads_an_archive(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertCount(1, Storage::disk('backups')->files('backups'));
    }

    public function test_the_backup_command_fails_gracefully_on_an_unknown_connection(): void
    {
        Storage::fake('backups');

        $this->artisan('backup:database --connection=nope')->assertFailed();
    }

    public function test_the_list_command_reports_when_there_is_nothing_stored(): void
    {
        Storage::fake('backups');

        $this->artisan('backup:list')->expectsOutputToContain('No backups found.')->assertSuccessful();
    }

    public function test_the_list_command_shows_stored_archives(): void
    {
        Storage::fake('backups');
        $this->seedDatabase();

        $this->artisan('backup:database')->assertSuccessful();
        $this->artisan('backup:list')->expectsOutputToContain('backup-test')->assertSuccessful();
    }

    public function test_the_clean_command_prunes_old_archives(): void
    {
        $disk = Storage::fake('backups');

        Storage::disk('backups')->put('backups/old.sql.gz', 'dump');
        touch(Storage::disk('backups')->path('backups/old.sql.gz'), now()->subDays(90)->getTimestamp());
        clearstatcache();

        $this->artisan('backup:clean --days=7 --keep-at-least=0')->assertSuccessful();

        $disk->assertMissing('backups/old.sql.gz');
    }

    public function test_the_clean_command_honours_the_keep_at_least_safety_net(): void
    {
        $disk = Storage::fake('backups');

        Storage::disk('backups')->put('backups/old.sql.gz', 'dump');
        touch(Storage::disk('backups')->path('backups/old.sql.gz'), now()->subDays(90)->getTimestamp());
        clearstatcache();

        // The default keep_at_least of 3 protects the only archive we have.
        $this->artisan('backup:clean --days=7')->assertSuccessful();

        $disk->assertExists('backups/old.sql.gz');
    }
}
