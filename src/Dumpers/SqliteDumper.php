<?php

namespace SahilJB\LaraAutoBackup\Dumpers;

use PDO;
use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;
use Throwable;

/**
 * Snapshots a SQLite database through PDO, so no sqlite3 CLI binary is needed.
 */
class SqliteDumper implements Dumper
{
    public function extension(): string
    {
        return 'sqlite';
    }

    public function dump(array $config, string $targetPath, array $options = []): void
    {
        $source = $config['database'] ?? '';

        if ($source === ':memory:' || ! is_file($source)) {
            throw BackupFailed::dumpFailed($source, 'SQLite database file not found.');
        }

        $compress = (bool) ($options['compress'] ?? false);
        $snapshot = $compress ? $targetPath . '.tmp' : $targetPath;

        try {
            $this->snapshot($source, $snapshot);

            if ($compress) {
                $this->gzip($snapshot, $targetPath);
                @unlink($snapshot);
            }
        } catch (BackupFailed $e) {
            @unlink($snapshot);
            @unlink($targetPath);

            throw $e;
        } catch (Throwable $e) {
            @unlink($snapshot);
            @unlink($targetPath);

            throw BackupFailed::dumpFailed($source, $e->getMessage());
        }

        if (! is_file($targetPath) || filesize($targetPath) === 0) {
            @unlink($targetPath);

            throw BackupFailed::emptyDump($source);
        }
    }

    private function snapshot(string $source, string $destination): void
    {
        $pdo = new PDO('sqlite:' . $source, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        try {
            // VACUUM INTO (SQLite 3.27+) writes a consistent snapshot even while
            // the database is being written to.
            $pdo->exec('VACUUM INTO ' . $pdo->quote($destination));

            return;
        } catch (Throwable) {
            // Older SQLite — fall back to a plain file copy below.
        } finally {
            $pdo = null;
        }

        if (! @copy($source, $destination)) {
            throw BackupFailed::dumpFailed($source, 'Unable to copy the SQLite database file.');
        }
    }

    private function gzip(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($destination, 'wb9');

        if ($in === false || $out === false) {
            throw BackupFailed::dumpFailed($source, 'Unable to compress the SQLite snapshot.');
        }

        try {
            while (! feof($in)) {
                gzwrite($out, (string) fread($in, 1024 * 512));
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }
}
