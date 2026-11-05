<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_assigned_to_every_branch(): void
    {
        $branch = Branch::factory()->create();

        $this->artisan('app:create-admin', ['--name' => 'Ana', '--email' => 'ana@example.com', '--password' => 'a-long-password'])
            ->assertSuccessful();

        $user = User::query()->where('email', 'ana@example.com')->sole();

        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue(Hash::check('a-long-password', $user->password));
        $this->assertTrue($user->branches()->whereKey($branch)->exists());
    }

    public function test_it_refuses_an_email_already_in_use_even_by_a_deleted_user(): void
    {
        $existing = User::factory()->trashed()->create();

        $this->artisan('app:create-admin', ['--name' => 'Ana', '--email' => $existing->email, '--password' => 'a-long-password'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
