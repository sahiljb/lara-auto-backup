<?php

namespace SahilJB\LaraAutoBackup\Events;

use Throwable;

class RestoreFailed
{
    public function __construct(
        public readonly string $connection,
        public readonly string $archive,
        public readonly Throwable $exception,
    ) {
    }
}
