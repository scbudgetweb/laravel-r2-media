<?php

namespace Scbudgetweb\R2Media;

use Illuminate\Support\ServiceProvider;
use Scbudgetweb\R2Media\Console\MigrateMediaCommand;
use Scbudgetweb\R2Media\Settings\SettingsRepository;

class R2MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/r2-media.php', 'r2-media');

        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        $this->registerDisks();

        $this->app->make(SettingsRepository::class)->apply();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'r2-media');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'r2-media');

        if ($this->app->runningInConsole()) {
            $this->commands([MigrateMediaCommand::class]);

            $this->publishes([
                __DIR__.'/../config/r2-media.php' => config_path('r2-media.php'),
            ], 'r2-media-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/r2-media'),
            ], 'r2-media-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/r2-media'),
            ], 'r2-media-translations');
        }
    }

    /**
     * Register the R2 and S3 disks. Keys the application already defines in
     * config/filesystems.php win; the package only fills in what is missing
     * (e.g. public visibility on Laravel's default "s3" disk).
     */
    private function registerDisks(): void
    {
        foreach ([R2Media::R2, R2Media::S3] as $option) {
            $diskName = R2Media::diskNameFor($option);

            config([
                "filesystems.disks.{$diskName}" => array_merge(
                    (array) config("r2-media.disks.{$option}", []),
                    (array) config("filesystems.disks.{$diskName}", []),
                ),
            ]);
        }
    }
}
