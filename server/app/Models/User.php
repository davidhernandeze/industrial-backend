<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Models\Concerns\AssignedToBranch;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use AssignedToBranch, Auditable, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            throw_if($user->isDirty('role'), LogicException::class, 'A user role is final.');
        });

        static::created(fn (self $user) => $user->assignAllBranchesIfAdmin());
        static::restored(fn (self $user) => $user->assignAllBranchesIfAdmin());
    }

    /**
     * Admins can always sign in. Everyone else needs at least one branch to work in.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->branches()->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Whether the user has exactly one of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Whether the user may do what the given roles do. Admins can do everything.
     */
    public function canActAs(Role ...$roles): bool
    {
        return $this->isAdmin() || $this->hasRole(...$roles);
    }

    /**
     * The branches the user can work in. Admins skip branch checks and reach all of them.
     *
     * @return Builder<Branch>|MorphToMany<Branch, $this>
     */
    public function accessibleBranches(): Builder|MorphToMany
    {
        return $this->isAdmin() ? Branch::query() : $this->branches();
    }

    public function belongsToBranch(Branch|int $branch): bool
    {
        return $this->accessibleBranches()->whereKey($branch)->exists();
    }

    private function assignAllBranchesIfAdmin(): void
    {
        if ($this->isAdmin()) {
            $this->branches()->syncWithoutDetaching(Branch::query()->pluck('id'));
        }
    }
}
