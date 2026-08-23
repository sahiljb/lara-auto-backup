<?php

namespace SahilJB\LaraAutoBackup\Dumpers;

interface Dumper
{
    /**
     * Write a dump of the given database to $targetPath.
     *
     * @param  array<string, mixed>  $connectionConfig  An entry from config('database.connections').
     * @param  array<string, mixed>  $options           Merged auto-backup options (compress, only_tables, …).
     */
    public function dump(array $connectionConfig, string $targetPath, array $options = []): void;

    /**
     * File extension for the archive this dumper produces, without a leading
     * dot (e.g. "sql"). A ".gz" suffix is added when compression is enabled.
     */
    public function extension(): string;
}
