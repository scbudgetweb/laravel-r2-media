<?php

namespace Scbudgetweb\R2Media\Settings;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Scbudgetweb\R2Media\R2Media;
use Throwable;

/**
 * Stores media storage settings (encrypted) in the database and pushes them
 * into config so they override env values without a deploy or restart.
 *
 * Only ciphertext is ever cached; values are decrypted when applied.
 */
class SettingsRepository
{
    public const MEDIA_DISK = 'media_disk';

    /**
     * @var array<string, array<int, string>>
     */
    public const DISK_FIELDS = [
        R2Media::R2 => ['key', 'secret', 'region', 'bucket', 'url', 'endpoint'],
        R2Media::S3 => ['key', 'secret', 'region', 'bucket', 'url', 'endpoint', 'use_path_style_endpoint'],
    ];

    /**
     * @var array<int, string>
     */
    public const SECRET_FIELDS = ['secret'];

    /**
     * @var array<int, string>
     */
    public const BOOLEAN_FIELDS = ['use_path_style_endpoint'];

    /**
     * Config values as they were before any database settings were applied.
     *
     * @var array<string, mixed>|null
     */
    private ?array $defaults = null;

    public function isEnabled(): bool
    {
        return (bool) config('r2-media.settings.enabled', true);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        $keys = [static::MEDIA_DISK];

        foreach (static::DISK_FIELDS as $option => $fields) {
            foreach ($fields as $field) {
                $keys[] = "{$option}.{$field}";
            }
        }

        return $keys;
    }

    public function isSecret(string $key): bool
    {
        return in_array($this->fieldOf($key), static::SECRET_FIELDS, true);
    }

    public function configKeyFor(string $key): string
    {
        if ($key === static::MEDIA_DISK) {
            return 'r2-media.media_disk';
        }

        if (! in_array($key, $this->keys(), true)) {
            throw new InvalidArgumentException("Unknown r2-media setting [{$key}].");
        }

        [$option, $field] = explode('.', $key, 2);

        return 'filesystems.disks.'.R2Media::diskNameFor($option).'.'.$field;
    }

    /**
     * Decrypted settings stored in the database. Empty when settings are
     * disabled or the table is unavailable (e.g. before migrations run).
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        try {
            $encrypted = $this->cache()->rememberForever(
                $this->cacheKey(),
                fn (): array => DB::table($this->table())->pluck('value', 'key')->all(),
            );
        } catch (Throwable) {
            return [];
        }

        $settings = [];

        foreach ($encrypted as $key => $value) {
            try {
                $settings[$key] = Crypt::decryptString($value);
            } catch (DecryptException) {
                // Encrypted with a different APP_KEY; ignore so env values are used instead.
            }
        }

        return $settings;
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Persist settings. Null or empty values remove the stored setting so the
     * env/config value is used again.
     *
     * @param  array<string, string|bool|null>  $values
     */
    public function save(array $values): void
    {
        $now = now();

        foreach ($values as $key => $value) {
            $this->configKeyFor($key);

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            if ($value === null || $value === '') {
                DB::table($this->table())->where('key', $key)->delete();

                continue;
            }

            DB::table($this->table())->updateOrInsert(
                ['key' => $key],
                ['value' => Crypt::encryptString((string) $value), 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $this->refresh();
    }

    /**
     * Push stored settings into config. Safe to call repeatedly: config is
     * reset to its pre-settings defaults first, so removed settings revert.
     */
    public function apply(): void
    {
        $this->defaults ??= $this->snapshotDefaults();

        config($this->defaults);

        foreach ($this->all() as $key => $value) {
            if (! in_array($key, $this->keys(), true)) {
                continue;
            }

            config([$this->configKeyFor($key) => $this->castValue($key, $value)]);
        }

        if (config('r2-media.set_media_library_default', true)) {
            config(['media-library.disk_name' => R2Media::disk()]);
        }

        $this->forgetResolvedDisks();
    }

    public function refresh(): void
    {
        try {
            $this->cache()->forget($this->cacheKey());
        } catch (Throwable) {
            // Cache unavailable; settings will be read from the database directly next time.
        }

        $this->apply();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotDefaults(): array
    {
        $defaults = [];

        foreach ($this->keys() as $key) {
            $configKey = $this->configKeyFor($key);
            $defaults[$configKey] = config($configKey);
        }

        return $defaults;
    }

    private function castValue(string $key, string $value): string|bool
    {
        if (in_array($this->fieldOf($key), static::BOOLEAN_FIELDS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    private function fieldOf(string $key): string
    {
        return str_contains($key, '.') ? explode('.', $key, 2)[1] : $key;
    }

    private function forgetResolvedDisks(): void
    {
        if (! app()->resolved('filesystem')) {
            return;
        }

        Storage::forgetDisk(array_values(R2Media::diskNames()));
    }

    private function cache(): CacheRepository
    {
        return Cache::store(config('r2-media.settings.cache_store'));
    }

    private function cacheKey(): string
    {
        return (string) config('r2-media.settings.cache_key', 'r2-media.settings');
    }

    private function table(): string
    {
        return (string) config('r2-media.settings.table', 'r2_media_settings');
    }
}
