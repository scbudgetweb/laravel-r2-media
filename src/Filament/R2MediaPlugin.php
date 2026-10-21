<?php

namespace Scbudgetweb\R2Media\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Scbudgetweb\R2Media\Filament\Pages\MediaStorageSettings;

/**
 * Adds the Media Storage settings page to a panel:
 * `$panel->plugin(R2MediaPlugin::make())`.
 */
class R2MediaPlugin implements Plugin
{
    /**
     * @var class-string<MediaStorageSettings>
     */
    protected string $page = MediaStorageSettings::class;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'r2-media';
    }

    /**
     * @param  class-string<MediaStorageSettings>  $page
     */
    public function page(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([$this->page]);
    }

    public function boot(Panel $panel): void {}
}
