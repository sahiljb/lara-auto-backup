<?php

namespace SahilJB\LaraAutoBackup\Events;

class RestoreCompleted
{
    public function __construct(
        public readonly string $connection,
        public readonly string $database,
        public readonly string $archive,
        public readonly float $duration,
    ) {
    }
}
