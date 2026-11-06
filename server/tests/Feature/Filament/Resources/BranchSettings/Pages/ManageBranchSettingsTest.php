<?php

namespace Tests\Feature\Filament\Resources\BranchSettings\Pages;

use App\Enums\Role;
use App\Enums\SettingType;
use App\Filament\Navigation\SidebarNavigation;
use App\Filament\Resources\BranchSettings\BranchSettingResource;
use App\Filament\Resources\BranchSettings\Pages\BranchSettingHistory;
use App\Filament\Resources\BranchSettings\Pages\ManageBranchSettings;
use App\Filament\Resources\BranchSettings\Pages\ViewBranchSetting;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingValue;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageBranchSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Branch $centro;

    private Branch $norte;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->centro = Branch::factory()->create(['name' => 'Centro']);
        $this->norte = Branch::factory()->create(['name' => 'Norte']);
        $this->manager = User::factory()->create(['role' => Role::BranchManager]);
        $this->manager->branches()->attach([$this->centro->id, $this->norte->id]);
        $this->actingAs($this->manager);
        $this->select($this->centro);
    }

    private function select(Branch $branch): void
    {
        $this->from('/admin')->post(route('filament.admin.current-branch'), ['branch_id' => $branch->id]);
        app()->forgetScopedInstances();
    }

    private function value(Branch $branch, BranchSetting $setting, array $attributes = []): BranchSettingValue
    {
        return BranchSettingValue::factory()->create([
            'branch_id' => $branch->id,
            'branch_setting_id' => $setting->id,
            ...$attributes,
        ]);
    }

    private function rowOf(Branch $branch, BranchSetting $setting): ?BranchSettingValue
    {
        return BranchSettingValue::query()->forBranch($branch)->where('branch_setting_id', $setting->id)->first();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get('/admin/branch-settings')->assertRedirect('/admin/login');
    }

    public function test_managers_and_admins_can_open_the_module(): void
    {
        $this->get('/admin/branch-settings')->assertOk();

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/branch-settings')->assertOk();
    }

    public function test_cashiers_and_drivers_cannot_open_the_module(): void
    {
        foreach ([Role::Cashier, Role::Driver] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->branches()->attach($this->centro);

            $this->actingAs($user)->get('/admin/branch-settings')->assertForbidden();
        }
    }

    public function test_every_setting_is_listed_even_without_a_row_or_a_value(): void
    {
        $unused = BranchSetting::factory()->create(['key' => 'unused']);
        $emptied = BranchSetting::factory()->create(['key' => 'emptied']);
        $used = BranchSetting::factory()->create(['key' => 'used']);
        $this->value($this->centro, $emptied, ['value' => null]);
        $this->value($this->centro, $used, ['value' => 'Hola']);

        Livewire::test(ManageBranchSettings::class)
            ->assertCanSeeTableRecords([$unused, $emptied, $used])
            ->assertSee('Hola');
    }

    public function test_only_the_value_of_the_current_branch_is_shown(): void
    {
        $setting = BranchSetting::factory()->create();
        $this->value($this->centro, $setting, ['value' => 'De Centro']);
        $this->value($this->norte, $setting, ['value' => 'De Norte']);

        Livewire::test(ManageBranchSettings::class)
            ->assertSee('De Centro')
            ->assertDontSee('De Norte');

        $this->select($this->norte);

        Livewire::test(ManageBranchSettings::class)
            ->assertSee('De Norte')
            ->assertDontSee('De Centro');
    }

    public function test_a_translated_setting_is_listed_by_its_name(): void
    {
        app('translator')->addLines(['branch_settings.keys.greeting' => 'Saludo'], 'es');
        BranchSetting::factory()->create(['key' => 'greeting']);

        Livewire::test(ManageBranchSettings::class)->assertSee('Saludo');
    }

    public function test_editing_a_setting_without_a_row_creates_it_for_the_current_branch(): void
    {
        $setting = BranchSetting::factory()->create();

        Livewire::test(ManageBranchSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => 'Hola'])
            ->assertHasNoFormErrors()
            ->assertSee('Hola');

        $this->assertSame('Hola', $this->rowOf($this->centro, $setting)->value);
        $this->assertNull($this->rowOf($this->norte, $setting));
    }

    public function test_the_form_shows_the_value_of_the_current_branch(): void
    {
        $setting = BranchSetting::factory()->create();
        $this->value($this->centro, $setting, ['value' => 'De Centro']);
        $this->value($this->norte, $setting, ['value' => 'De Norte']);

        Livewire::test(ManageBranchSettings::class)
            ->mountAction(TestAction::make('edit')->table($setting))
            ->assertSchemaStateSet(['value' => 'De Centro'], 'mountedActionSchema0');
    }

    public function test_editing_does_not_touch_other_branches(): void
    {
        $setting = BranchSetting::factory()->create();
        $this->value($this->centro, $setting, ['value' => 'De Centro']);
        $this->value($this->norte, $setting, ['value' => 'De Norte']);

        Livewire::test(ManageBranchSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => 'Nuevo'])
            ->assertHasNoFormErrors();

        $this->assertSame('Nuevo', $this->rowOf($this->centro, $setting)->value);
        $this->assertSame('De Norte', $this->rowOf($this->norte, $setting)->value);
    }

    public function test_a_value_can_be_emptied_keeping_its_row(): void
    {
        $setting = BranchSetting::factory()->create();
        $row = $this->value($this->centro, $setting, ['value' => 'Hola']);

        Livewire::test(ManageBranchSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => null])
            ->assertHasNoFormErrors()
            ->assertDontSee('Hola');

        $this->assertNotNull($row->fresh());
        $this->assertNull($row->fresh()->value);
    }

    public function test_a_number_rejects_text(): void
    {
        $setting = BranchSetting::factory()->create(['type' => SettingType::Number]);

        Livewire::test(ManageBranchSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['value' => 'many'])
            ->assertHasFormErrors(['value']);

        $this->assertNull($this->rowOf($this->centro, $setting));
    }

    public function test_a_price_rejects_more_than_two_decimals_and_negatives(): void
    {
        $setting = BranchSetting::factory()->create(['type' => SettingType::Price]);

        foreach (['1.234', '-1'] as $price) {
            Livewire::test(ManageBranchSettings::class)
                ->callAction(TestAction::make('edit')->table($setting), data: ['value' => $price])
                ->assertHasFormErrors(['value']);
        }

        $this->assertNull($this->rowOf($this->centro, $setting));
    }

    public function test_a_price_is_listed_as_money(): void
    {
        $setting = BranchSetting::factory()->create(['type' => SettingType::Price]);
        $this->value($this->centro, $setting, ['value' => '1234.5']);

        Livewire::test(ManageBranchSettings::class)->assertSee('$1,234.50');
    }

    public function test_an_entity_is_chosen_stored_and_listed_by_name(): void
    {
        $client = Client::factory()->create(['name' => 'Rosa']);
        $setting = BranchSetting::factory()->create(['type' => SettingType::Entity, 'entity_type' => Client::class]);

        Livewire::test(ManageBranchSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['entity_id' => $client->id])
            ->assertHasNoFormErrors()
            ->assertSee('Rosa');

        $row = $this->rowOf($this->centro, $setting);
        $this->assertSame($client->id, $row->entity_id);
        $this->assertNull($row->value);
    }

    public function test_an_entity_that_does_not_exist_is_rejected(): void
    {
        $setting = BranchSetting::factory()->create(['type' => SettingType::Entity, 'entity_type' => Client::class]);

        Livewire::test(ManageBranchSettings::class)
            ->callAction(TestAction::make('edit')->table($setting), data: ['entity_id' => 999])
            ->assertHasFormErrors(['entity_id']);

        $this->assertNull($this->rowOf($this->centro, $setting));
    }

    public function test_settings_cannot_be_created_or_deleted(): void
    {
        $setting = BranchSetting::factory()->create();

        Livewire::test(ManageBranchSettings::class)
            ->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist(TestAction::make('delete')->table($setting));
    }

    public function test_the_view_page_shows_the_value_of_the_current_branch(): void
    {
        $setting = BranchSetting::factory()->create();
        $this->value($this->centro, $setting, ['value' => 'De Centro']);
        $this->value($this->norte, $setting, ['value' => 'De Norte']);

        Livewire::test(ViewBranchSetting::class, ['record' => $setting->getRouteKey()])
            ->assertSee('De Centro')
            ->assertDontSee('De Norte');
    }

    public function test_the_view_page_can_edit_a_setting_without_a_row(): void
    {
        $setting = BranchSetting::factory()->create();

        Livewire::test(ViewBranchSetting::class, ['record' => $setting->getRouteKey()])
            ->assertActionHidden('history')
            ->callAction('edit', data: ['value' => 'Hola'])
            ->assertHasNoFormErrors()
            ->assertSee('Hola');

        $this->assertSame('Hola', $this->rowOf($this->centro, $setting)->value);
    }

    public function test_history_is_offered_only_once_the_branch_has_a_row(): void
    {
        $unused = BranchSetting::factory()->create();
        $used = BranchSetting::factory()->create();
        $this->value($this->centro, $used);
        $this->value($this->norte, $unused);

        Livewire::test(ManageBranchSettings::class)
            ->assertActionHidden(TestAction::make('history')->table($unused))
            ->assertActionVisible(TestAction::make('history')->table($used));

        $this->get(BranchSettingResource::getUrl('history', ['record' => $unused]))->assertNotFound();
    }

    public function test_history_shows_only_the_changes_of_the_current_branch(): void
    {
        $setting = BranchSetting::factory()->create();
        $this->value($this->centro, $setting, ['value' => 'Antes'])->update(['value' => 'Centro nuevo']);
        $this->value($this->norte, $setting, ['value' => 'Inicial'])->update(['value' => 'Norte nuevo']);

        Livewire::test(BranchSettingHistory::class, ['record' => $setting->getRouteKey()])
            ->assertOk()
            ->assertSee('Antes → Centro nuevo')
            ->assertDontSee('Norte nuevo');
    }

    public function test_history_names_the_chosen_entity(): void
    {
        $client = Client::factory()->create(['name' => 'Rosa']);
        $setting = BranchSetting::factory()->create(['type' => SettingType::Entity, 'entity_type' => Client::class]);
        $this->value($this->centro, $setting, ['value' => null, 'entity_id' => $client->id]);

        Livewire::test(BranchSettingHistory::class, ['record' => $setting->getRouteKey()])
            ->assertOk()
            ->assertSee('Valor: Rosa');
    }

    public function test_the_module_is_in_the_branch_section_of_the_sidebar(): void
    {
        $labels = fn (array $items): array => array_map(fn (NavigationItem $item): string => $item->getLabel(), $items);

        $this->assertContains('Configuraciones de sucursal', $labels(SidebarNavigation::branchItems()));
        $this->assertNotContains('Configuraciones de sucursal', $labels(SidebarNavigation::globalItems()));
    }
}
