<?php

namespace SahilJB\LaraAutoBackup\Events;

class BackupStarted
{
    public function __construct(
        public readonly string $connection,
        public readonly string $database,
    ) {
    }
}
