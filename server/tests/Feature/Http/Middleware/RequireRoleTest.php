<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\Role;
use App\Filament\Resources\RoleGuardedResource;
use App\Http\Middleware\RequireBranchAccess;
use App\Http\Middleware\RequireRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;
use ValueError;

class RequireRoleTest extends TestCase
{
    use RefreshDatabase;

    private function route(string $middleware): string
    {
        Route::get('/_test', fn () => 'ok')->middleware($middleware);

        return '/_test';
    }

    private function user(Role $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_a_listed_role_is_allowed(): void
    {
        $this->actingAs($this->user(Role::Driver))->get($this->route('role:cashier,driver'))->assertOk();
    }

    public function test_an_unlisted_role_is_forbidden(): void
    {
        $this->actingAs($this->user(Role::Driver))->get($this->route('role:cashier'))->assertForbidden();
    }

    public function test_guests_are_forbidden(): void
    {
        $this->get($this->route('role:cashier'))->assertForbidden();
    }

    public function test_admins_are_allowed_without_being_listed(): void
    {
        $this->actingAs($this->user(Role::Admin))->get($this->route('role:driver'))->assertOk();
    }

    public function test_admins_can_be_excluded_explicitly(): void
    {
        $this->actingAs($this->user(Role::Admin))->get($this->route('role:exclusive,driver'))->assertForbidden();
    }

    public function test_a_listed_role_passes_an_exclusive_check(): void
    {
        $this->actingAs($this->user(Role::Driver))->get($this->route('role:exclusive,driver'))->assertOk();
    }

    public function test_a_misspelled_role_fails_instead_of_letting_anyone_in(): void
    {
        $this->withoutExceptionHandling()->expectException(ValueError::class);

        $this->actingAs($this->user(Role::Driver))->get($this->route('role:drvier'));
    }

    public function test_the_guards_also_run_on_livewire_requests(): void
    {
        $this->assertContains(RequireRole::class, Livewire::getPersistentMiddleware());
        $this->assertContains(RequireBranchAccess::class, Livewire::getPersistentMiddleware());
    }

    public function test_every_resource_lists_its_allowed_roles(): void
    {
        foreach (glob(app_path('Filament/Resources/*/*Resource.php')) as $file) {
            $resource = 'App\\Filament\\Resources\\'.basename(dirname($file)).'\\'.basename($file, '.php');

            $this->assertTrue(is_subclass_of($resource, RoleGuardedResource::class), "[{$resource}] is not role guarded.");
            $this->assertStringContainsString('role:', implode(' ', (array) $resource::getRouteMiddleware(filament()->getDefaultPanel())));
        }
    }

    public function test_branch_access_allows_an_assigned_branch(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $user->branches()->attach($branch);
        Route::get('/_branch/{branch}', fn () => 'ok')->middleware('branch.access');

        $this->actingAs($user)->get("/_branch/{$branch->id}")->assertOk();
    }

    public function test_branch_access_rejects_a_route_branch_the_user_does_not_belong_to(): void
    {
        $branch = Branch::factory()->create();
        Route::get('/_branch/{branch}', fn () => 'ok')->middleware('branch.access');

        $this->actingAs(User::factory()->create())->get("/_branch/{$branch->id}")->assertForbidden();
    }

    public function test_branch_access_rejects_an_input_branch_the_user_does_not_belong_to(): void
    {
        $branch = Branch::factory()->create();
        Route::post('/_branch', fn () => 'ok')->middleware('branch.access');

        $this->actingAs(User::factory()->create())->post('/_branch', ['branch_id' => $branch->id])->assertForbidden();
    }

    public function test_branch_access_lets_admins_through_without_an_assignment(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->admin()->create();
        $admin->branches()->detach();
        Route::get('/_branch/{branch}', fn () => 'ok')->middleware('branch.access');

        $this->actingAs($admin)->get("/_branch/{$branch->id}")->assertOk();
    }

    public function test_branch_access_rejects_a_soft_deleted_branch(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $user->branches()->attach($branch);
        $branch->delete();
        Route::get('/_branch/{branch}', fn () => 'ok')->middleware('branch.access');

        $this->actingAs($user)->get("/_branch/{$branch->id}")->assertForbidden();
    }
}
