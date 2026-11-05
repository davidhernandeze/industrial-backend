<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Collection;

/**
 * The branch the signed-in user is working in, kept in the session.
 */
class CurrentBranch
{
    private const string SESSION_KEY = 'current_branch_id';

    /**
     * @var Collection<int, Branch>|null
     */
    private ?Collection $available = null;

    /**
     * The branches the signed-in user can work in.
     *
     * @return Collection<int, Branch>
     */
    public function available(): Collection
    {
        return $this->available ??= auth()->user()?->accessibleBranches()->orderBy('name')->get() ?? collect();
    }

    /**
     * Most users work in a single branch and never see any of this.
     */
    public function hasChoice(): bool
    {
        return $this->available()->count() > 1;
    }

    /**
     * The selected branch, or the first available one if the selection is no longer valid.
     */
    public function get(): ?Branch
    {
        return $this->find(session(self::SESSION_KEY)) ?? $this->available()->first();
    }

    public function find(mixed $id): ?Branch
    {
        return $this->available()->firstWhere('id', $id);
    }

    public function set(Branch $branch): void
    {
        session([self::SESSION_KEY => $branch->getKey()]);
    }
}
