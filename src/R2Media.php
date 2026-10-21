<?php

namespace Scbudgetweb\R2Media;

class R2Media
{
    public const LOCAL = 'local';

    public const R2 = 'r2';

    public const S3 = 's3';

    /**
     * The disk name media should be stored on.
     *
     * Use this in media collections instead of hardcoding a disk name:
     * `$this->addMediaCollection('file')->useDisk(R2Media::disk());`
     *
     * Falls back to the local disk when the configured disk is not one of the
     * package's disks or has no filesystem definition.
     */
    public static function disk(): string
    {
        $disk = config('r2-media.media_disk');

        if (is_string($disk) && in_array($disk, static::diskNames(), true) && config("filesystems.disks.{$disk}") !== null) {
            return $disk;
        }

        return static::diskNameFor(static::LOCAL);
    }

    /**
     * @return array<string, string> option => disk name
     */
    public static function diskNames(): array
    {
        return [
            static::LOCAL => (string) config('r2-media.disk_names.local', 'public'),
            static::R2 => (string) config('r2-media.disk_names.r2', 'r2'),
            static::S3 => (string) config('r2-media.disk_names.s3', 's3'),
        ];
    }

    public static function diskNameFor(string $option): string
    {
        return static::diskNames()[$option] ?? 'public';
    }

    public static function optionFor(string $disk): ?string
    {
        $option = array_search($disk, static::diskNames(), true);

        return $option === false ? null : $option;
    }

    public static function isRemote(string $disk): bool
    {
        return in_array(static::optionFor($disk), [static::R2, static::S3], true);
    }
}
