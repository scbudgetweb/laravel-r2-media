<?php

namespace Scbudgetweb\R2Media\Tests\Feature;

use Scbudgetweb\R2Media\R2Media;
use Scbudgetweb\R2Media\Tests\TestCase;

class R2MediaTest extends TestCase
{
    public function test_disk_returns_configured_media_disk(): void
    {
        config(['r2-media.media_disk' => 'r2']);

        $this->assertSame('r2', R2Media::disk());
    }

    public function test_disk_defaults_to_public(): void
    {
        $this->assertSame('public', R2Media::disk());
    }

    public function test_disk_falls_back_to_local_for_unknown_disk(): void
    {
        config(['r2-media.media_disk' => 'not-a-disk']);

        $this->assertSame('public', R2Media::disk());
    }

    public function test_disk_falls_back_to_local_when_disk_has_no_filesystem_definition(): void
    {
        config(['r2-media.media_disk' => 'r2', 'filesystems.disks.r2' => null]);

        $this->assertSame('public', R2Media::disk());
    }

    public function test_disk_names_follow_config(): void
    {
        config(['r2-media.disk_names.r2' => 'cloudflare']);

        $this->assertSame('cloudflare', R2Media::diskNameFor(R2Media::R2));
        $this->assertSame(R2Media::R2, R2Media::optionFor('cloudflare'));
        $this->assertNull(R2Media::optionFor('r2'));
    }

    public function test_is_remote(): void
    {
        $this->assertTrue(R2Media::isRemote('r2'));
        $this->assertTrue(R2Media::isRemote('s3'));
        $this->assertFalse(R2Media::isRemote('public'));
        $this->assertFalse(R2Media::isRemote('unknown'));
    }
}
