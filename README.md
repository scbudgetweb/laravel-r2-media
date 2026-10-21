# Laravel R2 Media

Configurable media storage for Laravel + Spatie Media Library. Store uploads locally, on **Cloudflare R2**, or on **Amazon S3**, and switch between them from env or from an admin page (no deploy or restart).

- Registers `r2` and `s3` filesystem disks at runtime (your `config/filesystems.php` values always win)
- Sets Spatie Media Library's default disk to the configured one (optional)
- Encrypted, database-backed settings that override env values on the next request
- Optional Filament (v4/v5) **Media Storage** settings page with a **Test connection** button
- `r2-media:migrate` command to copy existing media (originals, conversions, responsive images) to the new disk

## Installation

```bash
composer config repositories.r2-media vcs https://github.com/scbudgetweb/laravel-r2-media
composer require scbudgetweb/laravel-r2-media
php artisan migrate
```

### Filament settings page

Either register the plugin on a panel:

```php
use Scbudgetweb\R2Media\Filament\R2MediaPlugin;

$panel->plugin(R2MediaPlugin::make());
```

Or, to avoid editing the panel provider when the panel uses `->discoverPages()`, add an empty subclass in the discovered directory:

```php
namespace App\Filament\Admin\Pages;

class MediaStorageSettings extends \Scbudgetweb\R2Media\Filament\Pages\MediaStorageSettings {}
```

Access is checked with `$user->can(config('r2-media.filament.ability'))`. The default is `update settings` (works with a Gate ability or a Spatie Permission permission of that name); set it to `null` to allow any panel user, or override `canAccess()` in your subclass.

## Configuration

Local development needs nothing; media stays on the `public` disk.

```dotenv
R2_MEDIA_DISK=r2                  # public | r2 | s3

R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_URL=https://media.example.com  # bucket's public r2.dev URL or custom domain
R2_DEFAULT_REGION=auto

AWS_ACCESS_KEY_ID=                # only for the s3 option
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Precedence: **admin page (database) → env → default `public`**.

Publish the config to change disk names, the settings table, navigation or the access ability:

```bash
php artisan vendor:publish --tag=r2-media-config
```

| Key | Default | Purpose |
|---|---|---|
| `media_disk` | `R2_MEDIA_DISK` / `public` | Disk media is stored on |
| `set_media_library_default` | `true` | Point `media-library.disk_name` at the media disk |
| `disk_names` | `public` / `r2` / `s3` | Filesystem disk names per option |
| `settings.enabled` | `true` | Read DB settings (disable for env-only) |
| `filament.ability` | `update settings` | Ability required to open the page |

## Usage in models

With `set_media_library_default` on, every collection without an explicit disk follows the setting. To opt in per collection instead, **never hardcode the disk name**:

```php
use Scbudgetweb\R2Media\R2Media;

public function registerMediaCollections(): void
{
    $this->addMediaCollection('file')->singleFile()->useDisk(R2Media::disk());
}
```

### Reading media: stay disk-agnostic

Remote disks have no local path. Always stream:

```php
$stream = $media->stream();                       // ✅ any disk
Storage::disk($media->disk)->readStream($path);   // ✅ any disk

file_get_contents($media->getPath());             // ❌ local disks only
```

## Migrating existing media

After switching an environment to R2/S3:

```bash
php artisan r2-media:migrate --dry-run     # review
php artisan r2-media:migrate               # copy to the configured disk
php artisan r2-media:migrate --to=r2 --from=public
```

Files are streamed (safe for large videos), each media row is repointed only after all its files copy, and re-running is a no-op. Source files are **left in place**; delete them once the new disk is verified.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests use Orchestra Testbench with `Storage::fake('r2')`, so the R2 path is the one under test.

## License

MIT. See [LICENSE](LICENSE).
