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
