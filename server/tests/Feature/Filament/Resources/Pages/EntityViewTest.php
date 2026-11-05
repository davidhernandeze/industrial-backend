<?php

namespace Tests\Feature\Filament\Resources\Pages;

use App\Enums\Role;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Filament\Resources\Branches\Pages\ViewBranch;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Resources\Settings\Pages\ViewSetting;
use App\Filament\Resources\Settings\SettingResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntityViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->admin = User::factory()->admin()->create(['name' => 'Ana']);
        $this->actingAs($this->admin);
    }

    public function test_rows_link_to_the_view_route(): void
    {
        $branch = Branch::factory()->create();
        $setting = Setting::factory()->create();

        Livewire::test(ManageBranches::class)
            ->assertActionHasUrl(TestAction::make('view')->table($branch), BranchResource::getUrl('view', ['record' => $branch]));
        Livewire::test(ManageSettings::class)
            ->assertActionHasUrl(TestAction::make('view')->table($setting), SettingResource::getUrl('view', ['record' => $setting]));
        Livewire::test(ManageUsers::class)
            ->assertActionHasUrl(TestAction::make('view')->table($this->admin), UserResource::getUrl('view', ['record' => $this->admin]));
    }

    public function test_every_table_keeps_its_row_options_in_one_menu(): void
    {
        Branch::factory()->create();

        foreach (glob(app_path('Filament/Resources/*/*Resource.php')) as $file) {
            $resource = 'App\\Filament\\Resources\\'.basename(dirname($file)).'\\'.basename($file, '.php');
            $actions = Livewire::test($resource::getPages()['index']->getPage())->instance()->getTable()->getRecordActions();

            $this->assertCount(1, $actions, "[{$resource}] has row options outside the menu.");
            $this->assertInstanceOf(ActionGroup::class, $actions[0], "[{$resource}] has row options outside the menu.");

            foreach ($actions[0]->getFlatActions() as $action) {
                $this->assertFalse($action->isIconButton(), "[{$resource}] has an unlabeled option in its menu: {$action->getName()}.");
            }
        }
    }

    public function test_the_view_routes_open_over_http(): void
    {
        $branch = Branch::factory()->create();
        $setting = Setting::factory()->create();

        $this->get("/admin/branches/{$branch->id}")->assertOk();
        $this->get("/admin/settings/{$setting->id}")->assertOk();
        $this->get("/admin/users/{$this->admin->id}")->assertOk();
    }

    public function test_a_branch_shows_its_data(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'Centro',
            'address' => 'Av. Juárez 1',
            'google_maps_url' => 'https://maps.google.com/?q=centro',
            'opening_date' => '2026-01-15',
        ]);

        Livewire::test(ViewBranch::class, ['record' => $branch->getRouteKey()])
            ->assertSee('Centro')
            ->assertSee('Av. Juárez 1')
            ->assertSee('Ver mapa')
            ->assertSeeHtml('https://maps.google.com/?q=centro')
            ->assertSee('15/01/2026');
    }

    public function test_a_branch_without_optional_data_still_opens(): void
    {
        $branch = Branch::factory()->create(['address' => null, 'google_maps_url' => null, 'opening_date' => null]);

        Livewire::test(ViewBranch::class, ['record' => $branch->getRouteKey()])->assertOk();
    }

    public function test_a_soft_deleted_branch_can_be_viewed(): void
    {
        $branch = Branch::factory()->trashed()->create(['name' => 'Cerrada']);

        Livewire::test(ViewBranch::class, ['record' => $branch->getRouteKey()])->assertSee('Cerrada');
    }

    public function test_a_user_shows_their_role_label_and_branches(): void
    {
        $user = User::factory()->create(['name' => 'Luis', 'role' => Role::BranchManager]);
        $user->branches()->attach(Branch::factory()->create(['name' => 'Centro']));

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->assertSee('Luis')
            ->assertSee('Gerente de sucursal')
            ->assertSee('Centro');
    }

    public function test_a_user_view_never_shows_the_password(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->admin->getRouteKey()])
            ->assertDontSee($this->admin->password);
    }

    public function test_a_setting_shows_its_translated_name_key_and_value(): void
    {
        $setting = Setting::factory()->create(['key' => 'company_name', 'value' => 'La Familia']);

        Livewire::test(ViewSetting::class, ['record' => $setting->getRouteKey()])
            ->assertSee('Ver Nombre de la empresa')
            ->assertSee('company_name')
            ->assertSee('La Familia');
    }

    public function test_a_record_can_be_edited_from_its_view_page(): void
    {
        $branch = Branch::factory()->create(['name' => 'Centro']);

        Livewire::test(ViewBranch::class, ['record' => $branch->getRouteKey()])
            ->callAction('edit', data: ['name' => 'Norte'])
            ->assertHasNoFormErrors();

        $this->assertSame('Norte', $branch->fresh()->name);
    }

    public function test_the_role_stays_final_from_the_view_page(): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::factory()->create());

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->callAction('edit', data: ['role' => Role::Admin->value, 'name' => 'Otro'])
            ->assertHasNoFormErrors();

        $this->assertSame(Role::Cashier, $user->fresh()->role);
        $this->assertSame('Otro', $user->fresh()->name);
    }

    public function test_the_view_page_links_to_the_history(): void
    {
        $branch = Branch::factory()->create();

        Livewire::test(ViewBranch::class, ['record' => $branch->getRouteKey()])
            ->assertActionHasUrl('history', BranchResource::getUrl('history', ['record' => $branch]));
    }

    public function test_non_admins_cannot_open_view_routes(): void
    {
        $branch = Branch::factory()->create();
        $setting = Setting::factory()->create();
        $cashier = User::factory()->create();
        $cashier->branches()->attach($branch);

        $this->actingAs($cashier);

        foreach (["/admin/branches/{$branch->id}", "/admin/settings/{$setting->id}", "/admin/users/{$this->admin->id}"] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $branch = Branch::factory()->create();
        auth()->logout();

        $this->get("/admin/branches/{$branch->id}")->assertRedirect('/admin/login');
    }
}
