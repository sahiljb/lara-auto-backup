<?php

namespace SahilJB\LaraAutoBackup\Events;

use Throwable;

class BackupFailed
{
    public function __construct(
        public readonly string $connection,
        public readonly Throwable $exception,
    ) {
    }
}
