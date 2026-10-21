<?php

namespace Scbudgetweb\R2Media\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Scbudgetweb\R2Media\R2Media;
use Scbudgetweb\R2Media\Settings\SettingsRepository;
use Scbudgetweb\R2Media\Support\ConnectionTester;
use UnitEnum;

/**
 * Admin page to choose the media disk and manage R2/S3 credentials.
 *
 * Register it with R2MediaPlugin, or extend it in a panel's discovered
 * pages directory (see ->discoverPages()).
 */
class MediaStorageSettings extends Page
{
    protected string $view = 'r2-media::filament.pages.media-storage-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $ability = config('r2-media.filament.ability');

        return $ability === null || $user->can($ability);
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return (string) config('r2-media.filament.slug', 'media-storage');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = config('r2-media.filament.navigation_group');

        return is_string($group) ? __($group) : $group;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return config('r2-media.filament.navigation_icon');
    }

    public static function getNavigationSort(): ?int
    {
        return config('r2-media.filament.navigation_sort');
    }

    public static function getNavigationLabel(): string
    {
        return __('r2-media::r2-media.navigation_label');
    }

    public function getTitle(): string
    {
        return __('r2-media::r2-media.title');
    }

    public function mount(): void
    {
        $this->form->fill($this->currentValues());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('r2-media::r2-media.sections.disk'))
                    ->description(__('r2-media::r2-media.sections.disk_description'))
                    ->schema([
                        Select::make('media_disk')
                            ->label(__('r2-media::r2-media.fields.media_disk'))
                            ->options($this->diskOptions())
                            ->required()
                            ->live(),
                    ]),
                $this->diskSection(R2Media::R2),
                $this->diskSection(R2Media::S3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $previousDisk = R2Media::disk();

        $this->settings()->save($this->settingsFromState($state));

        $notification = Notification::make()
            ->title(__('r2-media::r2-media.notifications.saved'))
            ->success();

        if ($previousDisk !== R2Media::disk()) {
            $notification->body(__('r2-media::r2-media.notifications.disk_changed', ['disk' => R2Media::disk()]));
        }

        $notification->send();

        $this->form->fill($this->currentValues());
    }

    public function testConnection(): void
    {
        $state = $this->form->getState();

        $result = app(ConnectionTester::class)->test($this->diskConfigFromState($state));

        Notification::make()
            ->title($result->successful
                ? __('r2-media::r2-media.notifications.test_passed')
                : __('r2-media::r2-media.notifications.test_failed'))
            ->body($result->message)
            ->status($result->successful ? 'success' : 'danger')
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(__('r2-media::r2-media.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(fn () => $this->testConnection()),
        ];
    }

    protected function diskSection(string $option): Section
    {
        $isR2 = $option === R2Media::R2;

        $fields = [
            TextInput::make("{$option}.key")
                ->label(__('r2-media::r2-media.fields.key'))
                ->required(),
            TextInput::make("{$option}.secret")
                ->label(__('r2-media::r2-media.fields.secret'))
                ->password()
                ->revealable()
                ->required(fn (): bool => ! $this->hasStoredSecret($option))
                ->placeholder(fn (): ?string => $this->hasStoredSecret($option) ? __('r2-media::r2-media.fields.secret_saved') : null)
                ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : $state),
            TextInput::make("{$option}.bucket")
                ->label(__('r2-media::r2-media.fields.bucket'))
                ->required(),
            TextInput::make("{$option}.region")
                ->label(__('r2-media::r2-media.fields.region'))
                ->placeholder($isR2 ? 'auto' : 'eu-west-2')
                ->required(! $isR2),
            TextInput::make("{$option}.endpoint")
                ->label(__('r2-media::r2-media.fields.endpoint'))
                ->helperText(__("r2-media::r2-media.help.{$option}_endpoint"))
                ->url()
                ->required($isR2),
            TextInput::make("{$option}.url")
                ->label(__('r2-media::r2-media.fields.url'))
                ->helperText(__("r2-media::r2-media.help.{$option}_url"))
                ->url()
                ->required($isR2),
        ];

        if (! $isR2) {
            $fields[] = Toggle::make("{$option}.use_path_style_endpoint")
                ->label(__('r2-media::r2-media.fields.use_path_style_endpoint'));
        }

        return Section::make(__("r2-media::r2-media.sections.{$option}"))
            ->schema($fields)
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('media_disk') === R2Media::diskNameFor($option));
    }

    /**
     * @return array<string, string> disk name => label
     */
    protected function diskOptions(): array
    {
        return [
            R2Media::diskNameFor(R2Media::LOCAL) => __('r2-media::r2-media.options.local'),
            R2Media::diskNameFor(R2Media::R2) => __('r2-media::r2-media.options.r2'),
            R2Media::diskNameFor(R2Media::S3) => __('r2-media::r2-media.options.s3'),
        ];
    }

    /**
     * Current effective values (database setting, else env/config). Secrets
     * are never sent to the browser.
     *
     * @return array<string, mixed>
     */
    protected function currentValues(): array
    {
        $values = ['media_disk' => R2Media::disk()];

        foreach (SettingsRepository::DISK_FIELDS as $option => $fields) {
            foreach ($fields as $field) {
                $key = "{$option}.{$field}";

                $values[$option][$field] = $this->settings()->isSecret($key)
                    ? null
                    : config($this->settings()->configKeyFor($key));
            }
        }

        return $values;
    }

    /**
     * Only the selected disk's fields are saved; a blank secret keeps the
     * stored one.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, string|bool|null>
     */
    protected function settingsFromState(array $state): array
    {
        $settings = [SettingsRepository::MEDIA_DISK => $state['media_disk']];
        $option = R2Media::optionFor($state['media_disk']);

        foreach (SettingsRepository::DISK_FIELDS[$option] ?? [] as $field) {
            $key = "{$option}.{$field}";
            $value = $state[$option][$field] ?? null;

            if ($this->settings()->isSecret($key) && blank($value)) {
                continue;
            }

            $settings[$key] = $value;
        }

        return $settings;
    }

    /**
     * The selected disk's configuration with unsaved form values applied.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function diskConfigFromState(array $state): array
    {
        $config = (array) config("filesystems.disks.{$state['media_disk']}", []);
        $option = R2Media::optionFor($state['media_disk']);

        foreach (SettingsRepository::DISK_FIELDS[$option] ?? [] as $field) {
            $value = $state[$option][$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $config[$field] = $value;
        }

        return $config;
    }

    protected function hasStoredSecret(string $option): bool
    {
        return filled(config($this->settings()->configKeyFor("{$option}.secret")));
    }

    protected function settings(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }
}
