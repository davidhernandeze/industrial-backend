<?php

namespace Tests\Feature\Models;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): mixed
    {
        Filament::setCurrentPanel('admin');

        return Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate');
    }

    public function test_the_role_is_final(): void
    {
        $user = User::factory()->create(['role' => Role::Cashier]);

        $this->expectException(LogicException::class);

        $user->update(['role' => Role::Admin]);
    }

    public function test_other_attributes_can_still_change(): void
    {
        $user = User::factory()->create();

        $user->update(['name' => 'Nuevo']);

        $this->assertSame('Nuevo', $user->fresh()->name);
    }

    public function test_a_new_admin_is_assigned_to_every_existing_branch(): void
    {
        $branches = Branch::factory()->count(2)->create();

        $admin = User::factory()->admin()->create();

        $this->assertEqualsCanonicalizing($branches->pluck('id')->all(), $admin->branches()->pluck('branches.id')->all());
    }

    public function test_a_new_branch_is_assigned_to_every_admin(): void
    {
        $admins = User::factory()->admin()->count(2)->create();
        $cashier = User::factory()->create();

        $branch = Branch::factory()->create();

        $admins->each(fn (User $admin) => $this->assertTrue($admin->branches()->whereKey($branch)->exists()));
        $this->assertFalse($cashier->belongsToBranch($branch));
    }

    public function test_a_soft_deleted_admin_is_not_assigned_but_catches_up_when_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->delete();

        $branch = Branch::factory()->create();
        $this->assertFalse($admin->branches()->whereKey($branch)->exists());

        $admin->restore();
        $this->assertTrue($admin->branches()->whereKey($branch)->exists());
    }

    public function test_an_admin_can_work_in_a_branch_without_being_assigned_to_it(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->admin()->create();
        $admin->branches()->detach();

        $this->assertTrue($admin->belongsToBranch($branch));
    }

    public function test_an_admin_cannot_work_in_a_soft_deleted_or_missing_branch(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->belongsToBranch(Branch::factory()->trashed()->create()));
        $this->assertFalse($admin->belongsToBranch(999));
    }

    public function test_a_non_admin_still_needs_the_assignment(): void
    {
        $this->assertFalse(User::factory()->create()->belongsToBranch(Branch::factory()->create()));
    }

    public function test_a_branch_manager_is_treated_as_a_branch_user(): void
    {
        $manager = User::factory()->create(['role' => Role::BranchManager]);
        $branch = Branch::factory()->create();

        $this->assertFalse($manager->canAccessPanel(Filament::getDefaultPanel()));
        $this->assertFalse($manager->belongsToBranch($branch));

        $manager->branches()->attach($branch);

        $this->assertTrue($manager->canAccessPanel(Filament::getDefaultPanel()));
        $this->assertTrue($manager->belongsToBranch($branch));
    }

    public function test_a_user_with_a_branch_can_log_in(): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::factory()->create());

        $this->login($user)->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_can_log_in_with_several_branches(): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::factory()->count(3)->create());

        $this->login($user)->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_without_branches_cannot_log_in(): void
    {
        $this->login(User::factory()->create())->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_a_user_whose_only_branch_was_deleted_cannot_log_in(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $user->branches()->attach($branch);
        $branch->delete();

        $this->login($user)->assertHasFormErrors(['email']);
    }

    public function test_a_signed_in_user_is_locked_out_when_their_last_branch_is_removed(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $user->branches()->attach($branch);

        $this->actingAs($user)->get('/admin')->assertOk();

        $user->branches()->detach();

        $this->get('/admin')->assertForbidden();
    }

    public function test_an_admin_can_log_in_without_any_branch(): void
    {
        $admin = User::factory()->admin()->create();

        $this->login($admin)->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }
}
