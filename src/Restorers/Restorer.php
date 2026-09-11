<?php

namespace SahilJB\LaraAutoBackup\Restorers;

interface Restorer
{
    /**
     * Load the archive at $archivePath into the given database.
     *
     * @param  array<string, mixed>  $connectionConfig  An entry from config('database.connections').
     * @param  array<string, mixed>  $options           Merged auto-backup options.
     */
    public function restore(array $connectionConfig, string $archivePath, array $options = []): void;
}
