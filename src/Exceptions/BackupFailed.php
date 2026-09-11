<?php

namespace SahilJB\LaraAutoBackup\Exceptions;

use RuntimeException;

class BackupFailed extends RuntimeException
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self("No dumper registered for database driver [{$driver}]. Add one under the 'dumpers' key in config/auto-backup.php.");
    }

    public static function unknownConnection(string $connection): self
    {
        return new self("Database connection [{$connection}] is not configured.");
    }

    public static function unsupportedRestoreDriver(string $driver): self
    {
        return new self("No restorer registered for database driver [{$driver}]. Add one under the 'restorers' key in config/auto-backup.php.");
    }

    public static function restoreFailed(string $database, string $reason): self
    {
        return new self(trim("Restore of [{$database}] failed. {$reason}"));
    }

    public static function archiveNotFound(string $path, string $disk): self
    {
        return new self("Archive [{$path}] was not found on disk [{$disk}].");
    }

    public static function downloadFailed(string $path, string $disk): self
    {
        return new self("Unable to download [{$path}] from disk [{$disk}].");
    }

    public static function dumpFailed(string $database, string $reason): self
    {
        return new self(trim("Dump of [{$database}] failed. {$reason}"));
    }

    public static function emptyDump(string $database): self
    {
        return new self("Dump of [{$database}] produced an empty file.");
    }

    public static function temporaryDirectory(string $path): self
    {
        return new self("Unable to create the temporary backup directory [{$path}].");
    }

    public static function uploadFailed(string $disk, string $reason): self
    {
        return new self(trim("Upload to disk [{$disk}] failed. {$reason}"));
    }
}
