<?php

namespace SahilJB\LaraAutoBackup\Restorers;

use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;

/**
 * The SQLite archive is the database file itself, so restoring means putting it
 * back in place — no client binary involved.
 */
class SqliteRestorer implements Restorer
{
    public function restore(array $config, string $archivePath, array $options = []): void
    {
        if (! is_file($archivePath)) {
            throw BackupFailed::restoreFailed($config['database'] ?? '', "Archive [{$archivePath}] not found.");
        }

        $target = (string) ($config['database'] ?? '');

        if ($target === '' || $target === ':memory:') {
            throw BackupFailed::restoreFailed($target, 'The connection has no database file to restore into.');
        }

        // Stage beside the target, then rename — an interrupted restore can
        // never leave a half-written database file in place.
        $staged = $target . '.restoring';

        try {
            str_ends_with($archivePath, '.gz')
                ? $this->gunzip($archivePath, $staged)
                : $this->copy($archivePath, $staged);

            if (! @rename($staged, $target)) {
                throw BackupFailed::restoreFailed($target, 'Unable to move the restored database into place.');
            }
        } catch (BackupFailed $e) {
            @unlink($staged);

            throw $e;
        }
    }

    private function copy(string $source, string $destination): void
    {
        if (! @copy($source, $destination)) {
            throw BackupFailed::restoreFailed($destination, 'Unable to copy the archive into place.');
        }
    }

    private function gunzip(string $source, string $destination): void
    {
        $in = gzopen($source, 'rb');
        $out = fopen($destination, 'wb');

        if ($in === false || $out === false) {
            throw BackupFailed::restoreFailed($destination, 'Unable to decompress the archive.');
        }

        try {
            while (! gzeof($in)) {
                fwrite($out, (string) gzread($in, 1024 * 512));
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }
}
