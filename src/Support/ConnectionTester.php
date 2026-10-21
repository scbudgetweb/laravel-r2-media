<?php

namespace Scbudgetweb\R2Media\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks a disk configuration works by writing, reading back and deleting a
 * small probe file. Works on unsaved configuration (built on demand).
 */
class ConnectionTester
{
    public const PROBE_DIRECTORY = 'r2-media-connection-test';

    /**
     * @param  array<string, mixed>  $diskConfig
     */
    public function test(array $diskConfig): ConnectionTestResult
    {
        $path = static::PROBE_DIRECTORY.'/'.Str::uuid().'.txt';
        $contents = 'r2-media connection test '.now()->toIso8601String();

        try {
            $disk = Storage::build(array_merge($diskConfig, ['throw' => true]));

            $disk->put($path, $contents);
            $readBack = $disk->get($path);
            $disk->delete($path);
        } catch (Throwable $exception) {
            return new ConnectionTestResult(false, $exception->getMessage());
        }

        if ($readBack !== $contents) {
            return new ConnectionTestResult(false, __('r2-media::r2-media.test.mismatch'));
        }

        return new ConnectionTestResult(true, __('r2-media::r2-media.test.success'));
    }
}
