<?php

namespace Scbudgetweb\R2Media\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Mockery;
use Scbudgetweb\R2Media\Filament\Pages\MediaStorageSettings;
use Scbudgetweb\R2Media\R2Media;
use Scbudgetweb\R2Media\Settings\SettingsRepository;
use Scbudgetweb\R2Media\Support\ConnectionTester;
use Scbudgetweb\R2Media\Support\ConnectionTestResult;
use Scbudgetweb\R2Media\Tests\Fixtures\User;
use Scbudgetweb\R2Media\Tests\TestCase;

class MediaStorageSettingsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('update settings', fn (User $user): bool => $user->email === 'admin@example.com');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    }

    private function member(): User
    {
        return User::create(['name' => 'Member', 'email' => 'member@example.com', 'password' => 'password']);
    }

    public function test_admin_can_view_page(): void
    {
        $this->actingAs($this->admin());

        $this->get(MediaStorageSettings::getUrl())
            ->assertSuccessful()
            ->assertSee('Media Storage');
    }

    public function test_user_without_ability_is_forbidden(): void
    {
        $this->actingAs($this->member());

        $this->get(MediaStorageSettings::getUrl())->assertForbidden();
    }

    public function test_guest_cannot_access(): void
    {
        $this->assertFalse(MediaStorageSettings::canAccess());
    }

    public function test_null_ability_allows_any_authenticated_user(): void
    {
        config(['r2-media.filament.ability' => null]);

        $this->actingAs($this->member());

        $this->assertTrue(MediaStorageSettings::canAccess());
    }

    public function test_saving_r2_settings(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MediaStorageSettings::class)
            ->fillForm([
                'media_disk' => 'r2',
                'r2.key' => 'access-key',
                'r2.secret' => 'secret-key',
                'r2.bucket' => 'media',
                'r2.region' => 'auto',
                'r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
                'r2.url' => 'https://media.example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $settings = app(SettingsRepository::class);

        $this->assertSame('r2', $settings->get('media_disk'));
        $this->assertSame('secret-key', $settings->get('r2.secret'));
        $this->assertSame('media', config('filesystems.disks.r2.bucket'));
        $this->assertSame('r2', R2Media::disk());
    }

    public function test_r2_fields_are_required_when_r2_selected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MediaStorageSettings::class)
            ->fillForm([
                'media_disk' => 'r2',
                'r2.key' => null,
                'r2.secret' => null,
                'r2.bucket' => null,
                'r2.endpoint' => null,
                'r2.url' => null,
            ])
            ->call('save')
            ->assertHasFormErrors([
                'r2.key' => 'required',
                'r2.secret' => 'required',
                'r2.bucket' => 'required',
                'r2.endpoint' => 'required',
                'r2.url' => 'required',
            ]);
    }

    public function test_hidden_disk_fields_are_not_validated_or_saved(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MediaStorageSettings::class)
            ->fillForm([
                'media_disk' => 'public',
                'r2.bucket' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('r2_media_settings', ['key' => 'r2.bucket']);
    }

    public function test_blank_secret_keeps_stored_secret(): void
    {
        app(SettingsRepository::class)->save(['r2.secret' => 'original-secret']);

        $this->actingAs($this->admin());

        Livewire::test(MediaStorageSettings::class)
            ->assertSchemaStateSet(['r2.secret' => null])
            ->fillForm([
                'media_disk' => 'r2',
                'r2.key' => 'access-key',
                'r2.secret' => '',
                'r2.bucket' => 'media',
                'r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
                'r2.url' => 'https://media.example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('original-secret', app(SettingsRepository::class)->get('r2.secret'));
    }

    public function test_test_connection_reports_success(): void
    {
        $this->actingAs($this->admin());

        $tester = Mockery::mock(ConnectionTester::class);
        $tester->shouldReceive('test')->once()->andReturn(new ConnectionTestResult(true, 'ok'));
        $this->app->instance(ConnectionTester::class, $tester);

        Livewire::test(MediaStorageSettings::class)
            ->fillForm(['media_disk' => 'public'])
            ->callAction('testConnection')
            ->assertNotified('Connection successful');
    }

    public function test_test_connection_reports_failure_and_uses_unsaved_values(): void
    {
        $this->actingAs($this->admin());

        $tester = Mockery::mock(ConnectionTester::class);
        $tester->shouldReceive('test')
            ->once()
            ->withArgs(fn (array $config): bool => $config['bucket'] === 'unsaved-bucket' && $config['driver'] === 's3')
            ->andReturn(new ConnectionTestResult(false, 'Access denied'));
        $this->app->instance(ConnectionTester::class, $tester);

        Livewire::test(MediaStorageSettings::class)
            ->fillForm([
                'media_disk' => 'r2',
                'r2.key' => 'access-key',
                'r2.secret' => 'secret-key',
                'r2.bucket' => 'unsaved-bucket',
                'r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
                'r2.url' => 'https://media.example.com',
            ])
            ->callAction('testConnection')
            ->assertNotified('Connection failed');

        $this->assertDatabaseMissing('r2_media_settings', ['key' => 'r2.bucket']);
    }
}
