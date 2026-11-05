<?php

namespace App\Support;

use App\Models\BranchProduct;
use App\Services\Sales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The sale in progress at a till, kept per cashier and branch so a reload or a crash does not
 * lose it. Nothing reaches the database until the sale is paid. Prices are read live.
 */
class PosCart
{
    public function __construct(private int $userId, private int $branchId) {}

    public static function current(): self
    {
        return new self((int) auth()->id(), (int) app(CurrentBranch::class)->get()?->getKey());
    }

    /**
     * Identifies this sale, so submitting it twice cannot charge twice.
     */
    public function uuid(): string
    {
        return $this->state()['uuid'];
    }

    /**
     * @return array<int, string> Quantities keyed by branch_product id
     */
    public function lines(): array
    {
        return $this->state()['lines'];
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    public function add(BranchProduct $row, string $quantity = '1'): void
    {
        $state = $this->state();
        $state['lines'][$row->id] = bcadd($state['lines'][$row->id] ?? '0', $quantity, 3);

        $this->store($state);
    }

    public function set(int $branchProductId, string $quantity): void
    {
        $state = $this->state();
        $state['lines'][$branchProductId] = bcadd($quantity, '0', 3);

        $this->store($state);
    }

    public function remove(int $branchProductId): void
    {
        $state = $this->state();
        unset($state['lines'][$branchProductId]);

        $this->store($state);
    }

    /**
     * Ends this sale. The next one gets a new uuid.
     */
    public function clear(): void
    {
        Cache::forget($this->key());
    }

    /**
     * The cart's rows with their quantity and line total, newest first.
     *
     * @return Collection<int, BranchProduct>
     */
    public function items(): Collection
    {
        $lines = $this->lines();

        if ($lines === []) {
            return collect();
        }

        $rows = BranchProduct::query()
            ->where('branch_id', $this->branchId)
            ->whereKey(array_keys($lines))
            ->whereHas('product', fn (Builder $products) => $products->withoutTrashed())
            ->with('product')
            ->get()
            ->keyBy('id');

        return collect(array_reverse(array_keys($lines)))
            ->filter(fn (int $id): bool => $rows->has($id))
            ->map(fn (int $id): BranchProduct => $rows[$id]->setAttribute('cart_quantity', $lines[$id])
                ->setAttribute('cart_total', Sales::lineTotal($rows[$id]->price, $lines[$id])))
            ->values();
    }

    public function total(): string
    {
        return $this->items()->reduce(
            fn (string $sum, BranchProduct $row): string => bcadd($sum, $row->cart_total, 2),
            '0.00',
        );
    }

    /**
     * @return array{uuid: string, lines: array<int, string>}
     */
    private function state(): array
    {
        return Cache::get($this->key()) ?? $this->store(['uuid' => (string) Str::uuid(), 'lines' => []]);
    }

    /**
     * @param  array{uuid: string, lines: array<int, string>}  $state
     * @return array{uuid: string, lines: array<int, string>}
     */
    private function store(array $state): array
    {
        Cache::put($this->key(), $state, now()->addDay());

        return $state;
    }

    private function key(): string
    {
        return "pos:cart:{$this->userId}:{$this->branchId}";
    }
}
