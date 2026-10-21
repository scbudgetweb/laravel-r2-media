<?php

namespace Scbudgetweb\R2Media\Tests\Feature;

use Scbudgetweb\R2Media\Tests\TestCase;

class DiskRegistrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filesystems.disks.s3', [
            'driver' => 's3',
            'bucket' => 'app-defined-bucket',
            'region' => 'eu-west-2',
        ]);
    }

    public function test_r2_disk_is_registered_with_package_defaults(): void
    {
        $disk = config('filesystems.disks.r2');

        $this->assertSame('s3', $disk['driver']);
        $this->assertSame('auto', $disk['region']);
        $this->assertTrue($disk['use_path_style_endpoint']);
        $this->assertSame('public', $disk['visibility']);
    }

    public function test_app_defined_s3_values_are_not_overwritten(): void
    {
        $this->assertSame('app-defined-bucket', config('filesystems.disks.s3.bucket'));
        $this->assertSame('eu-west-2', config('filesystems.disks.s3.region'));
    }

    public function test_missing_s3_keys_are_filled_from_package_defaults(): void
    {
        $this->assertSame('public', config('filesystems.disks.s3.visibility'));
        $this->assertArrayHasKey('endpoint', config('filesystems.disks.s3'));
    }

    public function test_media_library_default_disk_follows_media_disk(): void
    {
        $this->assertSame('public', config('media-library.disk_name'));
    }
}
