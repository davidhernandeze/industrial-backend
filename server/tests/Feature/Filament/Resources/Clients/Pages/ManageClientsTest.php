<?php

namespace Tests\Feature\Filament\Resources\Clients\Pages;

use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PointOfSale;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageClientsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->branch = Branch::factory()->create(['name' => 'Alfa']);
    }

    private function userWith(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->branches()->syncWithoutDetaching($this->branch);

        return $user;
    }

    public function test_admins_and_managers_can_open_it(): void
    {
        foreach ([Role::Admin, Role::BranchManager] as $role) {
            $this->actingAs($this->userWith($role))
                ->get(Dashboard::getUrl())
                ->assertSee('href="'.ClientResource::getUrl().'"', escape: false);

            $this->get(ClientResource::getUrl())->assertOk();
        }
    }

    public function test_cashiers_and_drivers_are_forbidden(): void
    {
        $client = Client::factory()->create();

        foreach ([Role::Cashier, Role::Driver] as $role) {
            $this->actingAs($this->userWith($role));

            $this->get(ClientResource::getUrl())->assertForbidden();
            $this->get(ClientResource::getUrl('view', ['record' => $client]))->assertForbidden();
        }
    }

    public function test_the_menu_keeps_it_from_cashiers(): void
    {
        $this->actingAs($this->userWith(Role::Cashier))
            ->get(PointOfSale::getUrl())
            ->assertOk()
            ->assertDontSee(ClientResource::getUrl());
    }

    public function test_a_client_only_needs_a_name(): void
    {
        $this->actingAs($this->userWith(Role::BranchManager));

        Livewire::test(ManageClients::class)
            ->callAction('create', data: ['name' => 'Juana'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clients', ['name' => 'Juana', 'phone' => null, 'email' => null, 'tax_id' => null]);
    }

    public function test_the_name_is_required_and_the_email_must_be_valid(): void
    {
        $this->actingAs($this->userWith(Role::BranchManager));

        Livewire::test(ManageClients::class)
            ->callAction('create', data: ['name' => null, 'email' => 'no-es-correo'])
            ->assertHasFormErrors(['name' => 'required', 'email' => 'email']);
    }

    public function test_clients_can_share_contact_details(): void
    {
        Client::factory()->create(['phone' => '6141234567', 'email' => 'casa@example.com']);
        $this->actingAs($this->userWith(Role::BranchManager));

        Livewire::test(ManageClients::class)
            ->callAction('create', data: ['name' => 'Familiar', 'phone' => '6141234567', 'email' => 'casa@example.com'])
            ->assertHasNoFormErrors();

        $this->assertSame(2, Client::query()->where('phone', '6141234567')->count());
    }

    public function test_managers_see_every_client_whatever_their_branch(): void
    {
        $client = Client::factory()->create(['name' => 'Sin sucursal']);
        $this->actingAs($this->userWith(Role::BranchManager));

        Livewire::test(ManageClients::class)->assertCanSeeTableRecords([$client]);
    }

    public function test_a_deleted_client_can_be_viewed_and_restored(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->userWith(Role::BranchManager));

        Livewire::test(ManageClients::class)->callAction(TestAction::make(DeleteAction::getDefaultName())->table($client));
        $this->assertSoftDeleted($client);

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])->assertOk();

        Livewire::test(ManageClients::class, ['tableFilters' => ['trashed' => ['value' => 0]]])
            ->callAction(TestAction::make(RestoreAction::getDefaultName())->table($client));
        $this->assertNotSoftDeleted($client);
    }
}
