<?php

namespace SahilJB\LaraAutoBackup\Events;

use SahilJB\LaraAutoBackup\BackupResult;

class BackupCompleted
{
    public function __construct(public readonly BackupResult $result)
    {
    }
}
