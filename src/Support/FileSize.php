<?php

namespace SahilJB\LaraAutoBackup\Support;

class FileSize
{
    /**
     * Format a byte count for humans, e.g. 13024122 => "12.42 MB".
     */
    public static function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
