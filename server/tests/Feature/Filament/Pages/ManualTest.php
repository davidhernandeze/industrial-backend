<?php

namespace Tests\Feature\Filament\Pages;

use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Manual;
use App\Filament\Pages\PointOfSale;
use App\Models\Branch;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->branch = Branch::factory()->create();
    }

    private function userWith(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->branches()->syncWithoutDetaching($this->branch);

        return $user;
    }

    public function test_admins_and_managers_can_open_it_inside_the_panel(): void
    {
        foreach ([Role::Admin, Role::BranchManager] as $role) {
            $this->actingAs($this->userWith($role))
                ->get(Manual::getUrl())
                ->assertOk()
                ->assertSee('fi-sidebar', escape: false)
                ->assertSee(__('manual.title'));
        }
    }

    public function test_cashiers_and_drivers_are_forbidden(): void
    {
        foreach ([Role::Cashier, Role::Driver] as $role) {
            $this->actingAs($this->userWith($role))
                ->get(Manual::getUrl())
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(Manual::getUrl())->assertRedirect('/admin/login');
    }

    public function test_the_menu_links_to_it_for_admins_and_managers(): void
    {
        foreach ([Role::Admin, Role::BranchManager] as $role) {
            $this->actingAs($this->userWith($role))
                ->get(Dashboard::getUrl())
                ->assertSee('href="'.Manual::getUrl().'"', escape: false);
        }
    }

    public function test_the_menu_keeps_it_from_cashiers(): void
    {
        $this->actingAs($this->userWith(Role::Cashier))
            ->get(PointOfSale::getUrl())
            ->assertOk()
            ->assertDontSee(Manual::getUrl());
    }

    public function test_the_tutorial_runs_from_the_first_branch_to_the_point_of_sale(): void
    {
        $this->actingAs($this->userWith(Role::Admin))
            ->get(Manual::getUrl())
            ->assertSeeInOrder(array_column(trans('manual.sections'), 'title'))
            ->assertSeeInOrder([
                '<strong>Crear sucursal</strong>',
                '<strong>Crear producto</strong>',
                '<strong>Crear usuario</strong>',
                '<strong>Crear movimiento</strong>',
                '<strong>Confirmar venta</strong>',
            ], escape: false);
    }

    public function test_every_contents_link_has_a_target(): void
    {
        $html = $this->actingAs($this->userWith(Role::Admin))->get(Manual::getUrl())->getContent();

        foreach (array_keys(trans('manual.sections')) as $key) {
            $this->assertStringContainsString('href="#'.$key.'"', $html);
            $this->assertStringContainsString('id="'.$key.'"', $html);
        }
    }

    public function test_each_tutorial_step_names_the_role_that_performs_it(): void
    {
        $this->actingAs($this->userWith(Role::Admin))
            ->get(Manual::getUrl())
            ->assertSeeInOrder([
                'Paso 2: Crea la sucursal',
                Role::Admin->getLabel(),
                'Paso 7: Realiza una venta',
                Role::Cashier->getLabel(),
            ]);
    }
}
