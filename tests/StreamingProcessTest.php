<?php

namespace SahilJB\LaraAutoBackup\Tests;

use SahilJB\LaraAutoBackup\Dumpers\BaseDumper;
use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;
use SahilJB\LaraAutoBackup\Restorers\BaseRestorer;

/**
 * The MySQL and Postgres dumpers stream through an external client binary,
 * which isn't available in CI — these fakes stand in for it so the shared
 * streaming, compression and error handling still get covered.
 */
class StreamingProcessTest extends TestCase
{
    private string $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->target = sys_get_temp_dir() . '/lara-auto-backup-stream-' . uniqid();
    }

    protected function tearDown(): void
    {
        @unlink($this->target);

        parent::tearDown();
    }

    public function test_it_writes_process_output_to_an_uncompressed_file(): void
    {
        (new EchoDumper)->dump(['database' => 'test'], $this->target, ['compress' => false]);

        $this->assertSame('CREATE TABLE posts;', file_get_contents($this->target));
    }

    public function test_it_gzips_process_output_without_an_external_binary(): void
    {
        (new EchoDumper)->dump(['database' => 'test'], $this->target, ['compress' => true]);

        // Unreadable as plain text, but valid gzip.
        $this->assertNotSame('CREATE TABLE posts;', file_get_contents($this->target));
        $this->assertSame('CREATE TABLE posts;', (string) gzdecode((string) file_get_contents($this->target)));
    }

    public function test_it_reports_the_process_error_output_and_removes_the_partial_file(): void
    {
        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageMatches('/something broke/');

        try {
            (new FailingDumper)->dump(['database' => 'test'], $this->target, ['compress' => false]);
        } finally {
            $this->assertFileDoesNotExist($this->target);
        }
    }

    public function test_it_rejects_an_empty_dump(): void
    {
        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageMatches('/empty file/');

        (new EmptyDumper)->dump(['database' => 'test'], $this->target, ['compress' => false]);
    }

    public function test_the_restorer_feeds_a_gzipped_archive_to_the_client_on_stdin(): void
    {
        $archive = $this->target . '.gz';
        file_put_contents($archive, (string) gzencode('SELECT 1;'));

        $received = $this->target . '.received';
        (new CaptureRestorer($received))->restore(['database' => 'test'], $archive);

        $this->assertSame('SELECT 1;', file_get_contents($received));

        @unlink($archive);
        @unlink($received);
    }

    public function test_the_restorer_feeds_a_plain_archive_to_the_client_on_stdin(): void
    {
        file_put_contents($this->target, 'SELECT 2;');

        $received = $this->target . '.received';
        (new CaptureRestorer($received))->restore(['database' => 'test'], $this->target);

        $this->assertSame('SELECT 2;', file_get_contents($received));

        @unlink($received);
    }

    public function test_the_restorer_surfaces_a_failing_client(): void
    {
        file_put_contents($this->target, 'SELECT 3;');

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageMatches('/client exploded/');

        (new FailingRestorer)->restore(['database' => 'test'], $this->target);
    }
}

class EchoDumper extends BaseDumper
{
    protected function command(array $config, array $options): array
    {
        return [PHP_BINARY, '-r', 'echo "CREATE TABLE posts;";'];
    }
}

class FailingDumper extends BaseDumper
{
    protected function command(array $config, array $options): array
    {
        return [PHP_BINARY, '-r', 'fwrite(STDERR, "something broke"); exit(1);'];
    }
}

class EmptyDumper extends BaseDumper
{
    protected function command(array $config, array $options): array
    {
        return [PHP_BINARY, '-r', 'exit(0);'];
    }
}

class CaptureRestorer extends BaseRestorer
{
    public function __construct(private string $destination)
    {
    }

    protected function command(array $config, array $options): array
    {
        return [PHP_BINARY, '-r', 'file_put_contents($argv[1], stream_get_contents(STDIN));', $this->destination];
    }
}

class FailingRestorer extends BaseRestorer
{
    protected function command(array $config, array $options): array
    {
        return [PHP_BINARY, '-r', 'fwrite(STDERR, "client exploded"); exit(1);'];
    }
}
