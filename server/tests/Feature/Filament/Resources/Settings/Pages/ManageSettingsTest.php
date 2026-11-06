<?php

namespace Tests\Feature\Filament\Resources\Settings\Pages;

use App\Enums\SettingType;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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

    public function test_a_price_setting_is_listed_as_money(): void
    {
        Setting::factory()->create(['type' => SettingType::Price, 'value' => '1234.5']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)->assertSee('$1,234.50');
    }

    public function test_a_price_setting_rejects_more_than_two_decimals(): void
    {
        $setting = Setting::factory()->create(['type' => SettingType::Price, 'value' => '10']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => '1.234'])
            ->assertHasFormErrors(['value']);

        $this->assertSame('10', $setting->fresh()->value);
    }

    public function test_a_number_setting_rejects_text(): void
    {
        $setting = Setting::factory()->create(['type' => SettingType::Number, 'value' => '10']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => 'many'])
            ->assertHasFormErrors(['value']);
    }

    public function test_an_entity_setting_stores_the_chosen_record(): void
    {
        $client = Client::factory()->create(['name' => 'Rosa']);
        $setting = Setting::factory()->create(['type' => SettingType::Entity, 'value' => null, 'entity_type' => Client::class]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['entity_id' => $client->id])
            ->assertHasNoFormErrors()
            ->assertSee('Rosa');

        $this->assertSame($client->id, $setting->fresh()->entity_id);
        $this->assertSame(Client::class, $setting->fresh()->entity_type);
    }

    private function mainBranchSetting(): Setting
    {
        $this->seed(SettingSeeder::class);

        return Setting::query()->where('key', 'main_branch')->sole();
    }

    public function test_the_main_branch_starts_empty(): void
    {
        $setting = $this->mainBranchSetting();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->assertSee('Sucursal principal')
            ->assertTableColumnStateSet('display_value', null, $setting);
    }

    public function test_a_branch_can_be_chosen_as_the_main_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Centro']);
        $setting = $this->mainBranchSetting();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['entity_id' => $branch->id])
            ->assertHasNoFormErrors()
            ->assertTableColumnStateSet('display_value', 'Centro', $setting);

        $this->assertTrue($branch->is($setting->fresh()->parsed_value));
    }

    public function test_the_search_offers_branches_by_name(): void
    {
        Branch::factory()->create(['name' => 'Centro']);
        Branch::factory()->create(['name' => 'Norte']);
        $setting = $this->mainBranchSetting();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->mountAction(TestAction::make('edit')->table($setting))
            ->assertFormFieldExists('entity_id', 'mountedActionSchema0', function (Select $field): bool {
                return array_values($field->getSearchResults('cen')) === ['Centro'];
            });
    }

    public function test_branches_are_listed_without_searching(): void
    {
        Branch::factory()->create(['name' => 'Norte']);
        Branch::factory()->create(['name' => 'Centro']);
        $setting = $this->mainBranchSetting();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->mountAction(TestAction::make('edit')->table($setting))
            ->assertFormFieldExists('entity_id', 'mountedActionSchema0', function (Select $field): bool {
                return array_values($field->getOptions()) === ['Centro', 'Norte'];
            });
    }

    public function test_a_branch_beyond_the_listed_ones_can_still_be_chosen(): void
    {
        Branch::factory()->count(50)->sequence(fn ($sequence): array => ['name' => "A {$sequence->index}"])->create();
        $last = Branch::factory()->create(['name' => 'Z']);
        $setting = $this->mainBranchSetting();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['entity_id' => $last->id])
            ->assertHasNoFormErrors();

        $this->assertSame($last->id, $setting->fresh()->entity_id);
    }

    public function test_the_main_branch_can_be_emptied(): void
    {
        $branch = Branch::factory()->create();
        $setting = $this->mainBranchSetting();
        $setting->update(['entity_id' => $branch->id]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['entity_id' => null])
            ->assertHasNoFormErrors();

        $this->assertNull($setting->fresh()->entity_id);
        $this->assertNull($setting->fresh()->parsed_value);
        $this->assertSame(Branch::class, $setting->fresh()->entity_type);
    }

    public function test_a_branch_that_does_not_exist_is_rejected(): void
    {
        $setting = $this->mainBranchSetting();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['entity_id' => 999])
            ->assertHasFormErrors(['entity_id']);

        $this->assertNull($setting->fresh()->entity_id);
    }

    public function test_a_deleted_main_branch_reads_as_empty_and_returns_when_restored(): void
    {
        $branch = Branch::factory()->create();
        $setting = $this->mainBranchSetting();
        $setting->update(['entity_id' => $branch->id]);

        $branch->delete();
        $this->assertNull($setting->fresh()->parsed_value);

        $branch->restore();
        $this->assertTrue($branch->is($setting->fresh()->parsed_value));
    }
}
