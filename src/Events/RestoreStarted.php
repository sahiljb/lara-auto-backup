<?php

namespace SahilJB\LaraAutoBackup\Events;

class RestoreStarted
{
    public function __construct(
        public readonly string $connection,
        public readonly string $database,
        public readonly string $archive,
    ) {
    }
}
