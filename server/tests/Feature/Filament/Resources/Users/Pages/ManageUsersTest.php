<?php

namespace Tests\Feature\Filament\Resources\Users\Pages;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\Pages\UserHistory;
use App\Filament\Resources\Users\UserResource;
use App\Models\Branch;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ManageUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'a-long-password',
            'role' => Role::Cashier->value,
            ...$overrides,
        ];
    }

    public function test_a_user_is_created_with_a_role_and_branches(): void
    {
        $centro = Branch::factory()->create();
        $norte = Branch::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->validData(['branches' => [(string) $centro->id]]))
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'ana@example.com')->sole();

        $this->assertSame(Role::Cashier, $user->role);
        $this->assertTrue(Hash::check('a-long-password', $user->password));
        $this->assertTrue($user->belongsToBranch($centro));
        $this->assertFalse($user->belongsToBranch($norte));
    }

    public function test_a_non_admin_needs_at_least_one_branch(): void
    {
        foreach ([Role::BranchManager, Role::Cashier, Role::Driver] as $role) {
            Livewire::test(ManageUsers::class)
                ->callAction('create', data: $this->validData(['role' => $role->value, 'branches' => []]))
                ->assertHasFormErrors(['branches' => 'required']);
        }

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    public function test_a_branch_manager_is_created_with_their_branches(): void
    {
        $branch = Branch::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->validData([
                'role' => Role::BranchManager->value,
                'branches' => [(string) $branch->id],
            ]))
            ->assertHasNoFormErrors();

        $manager = User::query()->where('email', 'ana@example.com')->sole();

        $this->assertSame(Role::BranchManager, $manager->role);
        $this->assertTrue($manager->belongsToBranch($branch));
    }

    public function test_a_new_admin_gets_every_branch_without_choosing_them(): void
    {
        $branches = Branch::factory()->count(2)->create();

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->validData(['role' => Role::Admin->value]))
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'ana@example.com')->sole();

        $this->assertEqualsCanonicalizing($branches->pluck('id')->all(), $user->branches()->pluck('branches.id')->all());
    }

    public function test_the_role_is_required(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->validData(['role' => null]))
            ->assertHasFormErrors(['role' => 'required']);
    }

    public function test_the_email_must_be_unique(): void
    {
        $branch = Branch::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->validData(['email' => $this->admin->email, 'branches' => [(string) $branch->id]]))
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_editing_cannot_change_the_role(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $user->branches()->attach($branch);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: ['role' => Role::Admin->value, 'name' => 'Cambiado'])
            ->assertHasNoFormErrors();

        $this->assertSame(Role::Cashier, $user->fresh()->role);
        $this->assertSame('Cambiado', $user->fresh()->name);
    }

    public function test_branches_can_be_added_and_removed(): void
    {
        $centro = Branch::factory()->create();
        $norte = Branch::factory()->create();
        $user = User::factory()->create();
        $user->branches()->attach($centro);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: ['branches' => [(string) $norte->id]])
            ->assertHasNoFormErrors();

        $this->assertFalse($user->belongsToBranch($centro));
        $this->assertTrue($user->belongsToBranch($norte));
    }

    public function test_an_admin_has_no_branch_picker(): void
    {
        Livewire::test(ManageUsers::class)
            ->mountAction(TestAction::make('edit')->table($this->admin))
            ->assertFormFieldHidden('branches');
    }

    public function test_a_non_admin_has_the_branch_picker(): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::factory()->create());

        Livewire::test(ManageUsers::class)
            ->mountAction(TestAction::make('edit')->table($user))
            ->assertFormFieldVisible('branches');
    }

    public function test_editing_an_admin_keeps_their_branch_assignments(): void
    {
        $branch = Branch::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($this->admin), data: ['name' => 'Otro'])
            ->assertHasNoFormErrors();

        $this->assertTrue($this->admin->branches()->whereKey($branch)->exists());
    }

    public function test_an_assignment_to_a_soft_deleted_branch_survives_an_edit(): void
    {
        $active = Branch::factory()->create();
        $deleted = Branch::factory()->create();
        $user = User::factory()->create();
        $user->branches()->attach([$active->id, $deleted->id]);
        $deleted->delete();

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: ['name' => 'Otro'])
            ->assertHasNoFormErrors();

        $deleted->restore();

        $this->assertTrue($user->belongsToBranch($deleted));
    }

    public function test_a_soft_deleted_branch_cannot_be_assigned(): void
    {
        $active = Branch::factory()->create();
        $deleted = Branch::factory()->trashed()->create();
        $user = User::factory()->create();
        $user->branches()->attach($active);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: ['branches' => [(string) $active->id, (string) $deleted->id]])
            ->assertHasNoFormErrors();

        $this->assertSame([$active->id], $user->branches()->pluck('branches.id')->all());
    }

    public function test_the_password_stays_when_left_blank(): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::factory()->create());
        $hash = $user->password;

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: ['password' => null])
            ->assertHasNoFormErrors();

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_the_password_can_be_changed(): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::factory()->create());

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: ['password' => 'another-long-password'])
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('another-long-password', $user->fresh()->password));
    }

    public function test_a_user_can_be_soft_deleted_and_restored(): void
    {
        $user = User::factory()->create();

        Livewire::test(ManageUsers::class)->callAction(TestAction::make('delete')->table($user));
        $this->assertSoftDeleted($user);

        Livewire::test(ManageUsers::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($user));
        $this->assertNotSoftDeleted($user);
    }

    public function test_an_admin_cannot_delete_themselves(): void
    {
        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('delete')->table($this->admin));
    }

    public function test_the_history_shows_the_role_label(): void
    {
        $user = User::factory()->create(['role' => Role::Driver]);

        Livewire::test(UserHistory::class, ['record' => $user->getRouteKey()])
            ->assertSee('Rol: Repartidor');
    }

    public function test_non_admins_cannot_open_the_module(): void
    {
        $branch = Branch::factory()->create();

        foreach ([Role::BranchManager, Role::Cashier, Role::Driver] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->branches()->attach($branch);

            $this->actingAs($user);

            foreach (['/admin/users', '/admin/branches', '/admin/settings', UserResource::getUrl('history', ['record' => $this->admin])] as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_non_admins_do_not_see_admin_modules_in_the_menu(): void
    {
        $cashier = User::factory()->create();
        $cashier->branches()->attach(Branch::factory()->create());

        $this->actingAs($cashier)->get('/admin')
            ->assertOk()
            ->assertSee('Escritorio')
            ->assertDontSee('Usuarios')
            ->assertDontSee('Sucursales')
            ->assertDontSee('Configuraciones');
    }

    public function test_admins_can_open_every_module(): void
    {
        foreach (['/admin/users', '/admin/branches', '/admin/settings'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
