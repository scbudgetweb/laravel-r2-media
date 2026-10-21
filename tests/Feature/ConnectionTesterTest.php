<?php

namespace Scbudgetweb\R2Media\Tests\Feature;

use Illuminate\Support\Facades\File;
use Scbudgetweb\R2Media\Support\ConnectionTester;
use Scbudgetweb\R2Media\Tests\TestCase;

class ConnectionTesterTest extends TestCase
{
    public function test_working_disk_passes_and_probe_file_is_removed(): void
    {
        $root = sys_get_temp_dir().'/r2-media-test-'.uniqid();

        $result = app(ConnectionTester::class)->test(['driver' => 'local', 'root' => $root]);

        $this->assertTrue($result->successful);
        $this->assertSame([], File::allFiles($root));

        File::deleteDirectory($root);
    }

    public function test_broken_disk_fails_with_message(): void
    {
        $fileInsteadOfDirectory = tempnam(sys_get_temp_dir(), 'r2-media');

        $result = app(ConnectionTester::class)->test(['driver' => 'local', 'root' => $fileInsteadOfDirectory]);

        $this->assertFalse($result->successful);
        $this->assertNotSame('', $result->message);

        unlink($fileInsteadOfDirectory);
    }
}
