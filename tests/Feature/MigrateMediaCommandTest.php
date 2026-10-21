<?php

namespace Scbudgetweb\R2Media\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Scbudgetweb\R2Media\Tests\Fixtures\TestModel;
use Scbudgetweb\R2Media\Tests\TestCase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MigrateMediaCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('r2');
        Storage::fake('s3');
    }

    private function createMedia(string $disk = 'public'): Media
    {
        $model = TestModel::create(['name' => 'Example']);

        $media = $model->addMedia(UploadedFile::fake()->create('interview.mp4', 50, 'video/mp4'))
            ->toMediaCollection('default', $disk);

        Storage::disk($disk)->put("{$media->id}/conversions/interview-thumb.jpg", 'thumbnail');

        return $media;
    }

    public function test_dry_run_copies_nothing(): void
    {
        $media = $this->createMedia();

        $this->artisan('r2-media:migrate', ['--to' => 'r2', '--dry-run' => true])
            ->expectsOutputToContain('Would migrate')
            ->assertSuccessful();

        Storage::disk('r2')->assertMissing("{$media->id}/interview.mp4");
        $this->assertSame('public', $media->fresh()->disk);
    }

    public function test_copies_original_and_conversions_and_updates_record(): void
    {
        $media = $this->createMedia();

        $this->artisan('r2-media:migrate', ['--to' => 'r2'])->assertSuccessful();

        Storage::disk('r2')->assertExists("{$media->id}/interview.mp4");
        Storage::disk('r2')->assertExists("{$media->id}/conversions/interview-thumb.jpg");

        $media->refresh();
        $this->assertSame('r2', $media->disk);
        $this->assertSame('r2', $media->conversions_disk);
    }

    public function test_source_files_are_left_in_place(): void
    {
        $media = $this->createMedia();

        $this->artisan('r2-media:migrate', ['--to' => 'r2'])->assertSuccessful();

        Storage::disk('public')->assertExists("{$media->id}/interview.mp4");
    }

    public function test_is_idempotent(): void
    {
        $this->createMedia();

        $this->artisan('r2-media:migrate', ['--to' => 'r2'])->assertSuccessful();

        $this->artisan('r2-media:migrate', ['--to' => 'r2'])
            ->expectsOutputToContain('Nothing to migrate')
            ->assertSuccessful();
    }

    public function test_defaults_to_configured_media_disk(): void
    {
        $media = $this->createMedia();

        config(['r2-media.media_disk' => 'r2']);

        $this->artisan('r2-media:migrate')->assertSuccessful();

        $this->assertSame('r2', $media->fresh()->disk);
    }

    public function test_missing_original_fails_and_leaves_record_unchanged(): void
    {
        $media = $this->createMedia();
        Storage::disk('public')->delete("{$media->id}/interview.mp4");

        $this->artisan('r2-media:migrate', ['--to' => 'r2'])->assertFailed();

        $this->assertSame('public', $media->fresh()->disk);
    }

    public function test_from_option_only_migrates_matching_disk(): void
    {
        $publicMedia = $this->createMedia('public');
        $s3Media = $this->createMedia('s3');

        $this->artisan('r2-media:migrate', ['--to' => 'r2', '--from' => 'public'])->assertSuccessful();

        $this->assertSame('r2', $publicMedia->fresh()->disk);
        $this->assertSame('s3', $s3Media->fresh()->disk);
    }

    public function test_unknown_disk_fails(): void
    {
        $this->artisan('r2-media:migrate', ['--to' => 'nope'])->assertFailed();
    }
}
