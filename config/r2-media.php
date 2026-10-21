<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk media should be stored on. Must be one of the disk
    | names in "disk_names" below. Local development can stay on "public"
    | with no setup; staging/production switch to "r2" or "s3".
    |
    | Precedence: admin settings page (database) → env → this default.
    |
    */

    'media_disk' => env('R2_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Spatie Media Library Default Disk
    |--------------------------------------------------------------------------
    |
    | When enabled, "media-library.disk_name" is set to the media disk above,
    | so every media collection without an explicit ->useDisk() follows it.
    | Disable to opt in per collection with ->useDisk(R2Media::disk()).
    |
    */

    'set_media_library_default' => (bool) env('R2_MEDIA_SET_MEDIA_LIBRARY_DEFAULT', true),

    /*
    |--------------------------------------------------------------------------
    | Disk Names
    |--------------------------------------------------------------------------
    |
    | The filesystem disk names used for each storage option.
    |
    */

    'disk_names' => [
        'local' => 'public',
        'r2' => 'r2',
        's3' => 's3',
    ],

    /*
    |--------------------------------------------------------------------------
    | Disk Definitions
    |--------------------------------------------------------------------------
    |
    | Registered under the disk names above at boot, only if the application
    | has not already defined a disk with that name in config/filesystems.php.
    |
    */

    'disks' => [

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Settings saved from the admin page are stored encrypted in this table
    | and pushed into config on every request. Set "enabled" to false to
    | rely on env/config only.
    |
    */

    'settings' => [
        'enabled' => (bool) env('R2_MEDIA_SETTINGS_ENABLED', true),
        'table' => 'r2_media_settings',
        'cache_key' => 'r2-media.settings',
        'cache_store' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Settings Page
    |--------------------------------------------------------------------------
    |
    | "ability" is checked with $user->can(). Null allows any authenticated
    | panel user. The default matches SaaSykit's "update settings" permission.
    |
    */

    'filament' => [
        'ability' => 'update settings',
        'navigation_group' => 'Settings',
        'navigation_sort' => null,
        'navigation_icon' => 'heroicon-o-cloud',
        'slug' => 'media-storage',
    ],

];
