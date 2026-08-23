<?php

namespace SahilJB\LaraAutoBackup\Tests;

use Illuminate\Support\Facades\Storage;
use SahilJB\LaraAutoBackup\BackupManager;

class RetentionTest extends TestCase
{
    public function test_it_prunes_archives_older_than_the_retention_window(): void
    {
        $disk = Storage::fake('backups');

        $this->putBackup('backups/old.sql.gz', now()->subDays(30));
        $this->putBackup('backups/recent.sql.gz', now()->subDay());

        $deleted = $this->app->make(BackupManager::class)->prune([
            'retention' => ['days' => 14, 'keep_at_least' => 0],
        ]);

        $this->assertSame(1, $deleted);
        $disk->assertMissing('backups/old.sql.gz');
        $disk->assertExists('backups/recent.sql.gz');
    }

    public function test_it_always_keeps_the_configured_minimum_number_of_archives(): void
    {
        $disk = Storage::fake('backups');

        $this->putBackup('backups/oldest.sql.gz', now()->subDays(60));
        $this->putBackup('backups/older.sql.gz', now()->subDays(40));
        $this->putBackup('backups/old.sql.gz', now()->subDays(30));

        $deleted = $this->app->make(BackupManager::class)->prune([
            'retention' => ['days' => 14, 'keep_at_least' => 2],
        ]);

        // All three are past the window, but the two newest are protected.
        $this->assertSame(1, $deleted);
        $disk->assertExists('backups/old.sql.gz');
        $disk->assertExists('backups/older.sql.gz');
        $disk->assertMissing('backups/oldest.sql.gz');
    }

    public function test_it_does_not_prune_when_retention_is_disabled(): void
    {
        $disk = Storage::fake('backups');

        $this->putBackup('backups/ancient.sql.gz', now()->subYears(2));

        $deleted = $this->app->make(BackupManager::class)->prune(['retention' => ['days' => 0]]);

        $this->assertSame(0, $deleted);
        $disk->assertExists('backups/ancient.sql.gz');
    }

    public function test_it_lists_stored_backups_newest_first(): void
    {
        Storage::fake('backups');

        $this->putBackup('backups/old.sql.gz', now()->subDays(5));
        $this->putBackup('backups/new.sql.gz', now()->subMinute());

        $listed = $this->app->make(BackupManager::class)->list('backups');

        $this->assertSame('backups/new.sql.gz', $listed[0]['path']);
        $this->assertSame('backups/old.sql.gz', $listed[1]['path']);
    }

    private function putBackup(string $path, \DateTimeInterface $modifiedAt): void
    {
        Storage::disk('backups')->put($path, 'dump');

        // Storage::fake is a local disk, so the mtime is settable directly.
        touch(Storage::disk('backups')->path($path), $modifiedAt->getTimestamp());
        clearstatcache();
    }
}
