<?php

return [
    'title' => 'Media Storage',
    'navigation_label' => 'Media Storage',

    'sections' => [
        'disk' => 'Storage location',
        'disk_description' => 'Where uploaded media is stored. Changes take effect immediately.',
        'r2' => 'Cloudflare R2',
        's3' => 'Amazon S3',
    ],

    'options' => [
        'local' => 'Local (this server)',
        'r2' => 'Cloudflare R2',
        's3' => 'Amazon S3',
    ],

    'fields' => [
        'media_disk' => 'Store media on',
        'key' => 'Access key ID',
        'secret' => 'Secret access key',
        'secret_saved' => 'Saved. Leave blank to keep the current key.',
        'bucket' => 'Bucket',
        'region' => 'Region',
        'endpoint' => 'Endpoint',
        'url' => 'Public URL',
        'use_path_style_endpoint' => 'Use path-style endpoint',
    ],

    'help' => [
        'r2_endpoint' => 'https://<account-id>.r2.cloudflarestorage.com',
        'r2_url' => 'The bucket\'s public r2.dev URL or custom domain.',
        's3_endpoint' => 'Leave blank for standard AWS S3.',
        's3_url' => 'Optional CDN or custom domain in front of the bucket.',
    ],

    'actions' => [
        'save' => 'Save changes',
        'test_connection' => 'Test connection',
    ],

    'notifications' => [
        'saved' => 'Media storage settings saved',
        'disk_changed' => 'New uploads now go to [:disk]. Run `php artisan r2-media:migrate` to copy existing media across.',
        'test_passed' => 'Connection successful',
        'test_failed' => 'Connection failed',
    ],

    'test' => [
        'success' => 'A test file was written, read back and deleted.',
        'mismatch' => 'The test file was written but read back different contents.',
    ],

    'migrate' => [
        'missing_original' => 'Original file not found on its disk.',
        'read_failed' => 'Could not read :path.',
        'write_failed' => 'Could not write :path.',
    ],
];
