<?php

namespace SahilJB\LaraAutoBackup\Facades;

use Illuminate\Support\Facades\Facade;
use SahilJB\LaraAutoBackup\BackupManager;

/**
 * @method static array<int, \SahilJB\LaraAutoBackup\BackupResult> run(array $overrides = [])
 * @method static \SahilJB\LaraAutoBackup\BackupResult backupConnection(string $connection, array $overrides = [])
 * @method static int prune(array $overrides = [])
 * @method static array<int, array{path: string, size: int, last_modified: int}> list(?string $disk = null, array $overrides = [])
 * @method static \SahilJB\LaraAutoBackup\BackupManager extend(string $driver, callable $resolver)
 * @method static \SahilJB\LaraAutoBackup\BackupManager onProgress(callable $reporter)
 *
 * @see \SahilJB\LaraAutoBackup\BackupManager
 */
class Backup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BackupManager::class;
    }
}
