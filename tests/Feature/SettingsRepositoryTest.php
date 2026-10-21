<?php

namespace Scbudgetweb\R2Media\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Scbudgetweb\R2Media\R2Media;
use Scbudgetweb\R2Media\Settings\SettingsRepository;
use Scbudgetweb\R2Media\Tests\TestCase;

class SettingsRepositoryTest extends TestCase
{
    private function repository(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }

    public function test_values_are_stored_encrypted(): void
    {
        $this->repository()->save(['r2.secret' => 'super-secret']);

        $stored = DB::table('r2_media_settings')->where('key', 'r2.secret')->value('value');

        $this->assertNotSame('super-secret', $stored);
        $this->assertSame('super-secret', Crypt::decryptString($stored));
    }

    public function test_saved_values_are_pushed_into_config(): void
    {
        $this->repository()->save([
            'r2.bucket' => 'media-bucket',
            'r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
            's3.region' => 'eu-west-2',
        ]);

        $this->assertSame('media-bucket', config('filesystems.disks.r2.bucket'));
        $this->assertSame('https://account.r2.cloudflarestorage.com', config('filesystems.disks.r2.endpoint'));
        $this->assertSame('eu-west-2', config('filesystems.disks.s3.region'));
    }

    public function test_saving_media_disk_switches_disk_and_media_library_default(): void
    {
        $this->repository()->save(['media_disk' => 'r2']);

        $this->assertSame('r2', R2Media::disk());
        $this->assertSame('r2', config('media-library.disk_name'));
    }

    public function test_media_library_default_is_untouched_when_disabled(): void
    {
        config(['r2-media.set_media_library_default' => false, 'media-library.disk_name' => 'custom']);

        $this->repository()->save(['media_disk' => 'r2']);

        $this->assertSame('custom', config('media-library.disk_name'));
    }

    public function test_empty_value_removes_setting_and_reverts_to_default(): void
    {
        $original = config('filesystems.disks.r2.bucket');

        $this->repository()->save(['r2.bucket' => 'temporary-bucket']);
        $this->assertSame('temporary-bucket', config('filesystems.disks.r2.bucket'));

        $this->repository()->save(['r2.bucket' => '']);

        $this->assertSame($original, config('filesystems.disks.r2.bucket'));
        $this->assertDatabaseMissing('r2_media_settings', ['key' => 'r2.bucket']);
    }

    public function test_boolean_values_are_cast(): void
    {
        $this->repository()->save(['s3.use_path_style_endpoint' => true]);
        $this->assertTrue(config('filesystems.disks.s3.use_path_style_endpoint'));

        $this->repository()->save(['s3.use_path_style_endpoint' => false]);
        $this->assertFalse(config('filesystems.disks.s3.use_path_style_endpoint'));
    }

    public function test_cache_only_holds_ciphertext(): void
    {
        $this->repository()->save(['r2.secret' => 'super-secret']);
        $this->repository()->all();

        $cached = Cache::get('r2-media.settings');

        $this->assertIsArray($cached);
        $this->assertArrayHasKey('r2.secret', $cached);
        $this->assertNotSame('super-secret', $cached['r2.secret']);
    }

    public function test_cache_is_refreshed_on_save(): void
    {
        $this->repository()->save(['r2.bucket' => 'first']);
        $this->repository()->save(['r2.bucket' => 'second']);

        $this->assertSame('second', $this->repository()->get('r2.bucket'));
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository()->save(['app.name' => 'Nope']);
    }

    public function test_settings_are_ignored_when_disabled(): void
    {
        $this->repository()->save(['r2.bucket' => 'stored-bucket']);

        config(['r2-media.settings.enabled' => false]);

        $this->assertSame([], $this->repository()->all());
    }

    public function test_missing_table_falls_back_to_config(): void
    {
        Schema::drop('r2_media_settings');

        $this->repository()->refresh();

        $this->assertSame([], $this->repository()->all());
        $this->assertSame('public', R2Media::disk());
    }

    public function test_values_that_cannot_be_decrypted_are_ignored(): void
    {
        DB::table('r2_media_settings')->insert([
            'key' => 'r2.bucket',
            'value' => 'not-encrypted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->repository()->refresh();

        $this->assertNull($this->repository()->get('r2.bucket'));
    }

    public function test_secret_detection(): void
    {
        $this->assertTrue($this->repository()->isSecret('r2.secret'));
        $this->assertTrue($this->repository()->isSecret('s3.secret'));
        $this->assertFalse($this->repository()->isSecret('r2.key'));
        $this->assertFalse($this->repository()->isSecret('media_disk'));
    }
}
