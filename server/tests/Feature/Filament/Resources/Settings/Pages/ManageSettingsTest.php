<?php

namespace Tests\Feature\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/admin/settings')->assertRedirect('/admin/login');
    }

    public function test_settings_are_listed_by_their_translated_name(): void
    {
        $this->seed(SettingSeeder::class);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->assertSee('Nombre de la empresa')
            ->assertSee('La Familia');
    }

    public function test_a_setting_without_a_translation_is_listed_by_its_key(): void
    {
        Setting::factory()->create(['key' => 'untranslated_setting']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)->assertSee('untranslated_setting');
    }

    public function test_the_value_of_a_setting_can_be_changed(): void
    {
        $setting = Setting::factory()->create(['key' => 'company_name', 'value' => 'Old']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => 'New'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', ['key' => 'company_name', 'value' => 'New']);
    }

    public function test_the_value_of_a_setting_can_be_cleared(): void
    {
        $setting = Setting::factory()->create(['value' => 'Old']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => null])
            ->assertHasNoFormErrors();

        $this->assertNull($setting->fresh()->value);
    }

    public function test_a_value_longer_than_the_column_is_rejected(): void
    {
        $setting = Setting::factory()->create(['value' => 'Old']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => str_repeat('a', 65536)])
            ->assertHasFormErrors(['value' => 'max']);

        $this->assertSame('Old', $setting->fresh()->value);
    }

    public function test_the_key_of_a_setting_cannot_be_changed(): void
    {
        $setting = Setting::factory()->create(['key' => 'company_name']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['key' => 'changed', 'value' => 'New']);

        $this->assertSame('company_name', $setting->fresh()->key);
    }

    public function test_settings_cannot_be_created_or_deleted(): void
    {
        $setting = Setting::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist(TestAction::make('delete')->table($setting));
    }
}
