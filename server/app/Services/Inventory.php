<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of branch_product.stock and inventory_movements.
 *
 * Reading stock never sums movements: the current balance is stored on the row. A movement
 * and its balance change commit together, under a row lock so balance_after stays exact.
 * A document that records several movements, such as App\Services\Sales, calls this in
 * ascending branch_product_id order inside one transaction, so two cannot deadlock.
 */
class Inventory
{
    public function record(
        BranchProduct $branchProduct,
        MovementType $type,
        string $quantity,
        ?string $note = null,
        ?Model $reference = null,
        ?string $unitPrice = null,
    ): InventoryMovement {
        if (bccomp($quantity, '0', 3) === 0) {
            throw new InvalidArgumentException('A movement needs a quantity.');
        }

        return DB::transaction(function () use ($branchProduct, $type, $quantity, $note, $reference, $unitPrice): InventoryMovement {
            $row = BranchProduct::withTrashed()->lockForUpdate()->findOrFail($branchProduct->getKey());

            $signed = $type->signedQuantity($quantity);
            $balance = bcadd($row->stock, $signed, 3);

            // Query builder on purpose: the ledger is the audit trail for stock.
            BranchProduct::withTrashed()->whereKey($row->getKey())->toBase()->update(['stock' => $balance]);

            return InventoryMovement::create([
                'branch_id' => $row->branch_id,
                'branch_product_id' => $row->getKey(),
                'type' => $type,
                'quantity' => $signed,
                'balance_after' => $balance,
                'unit_price' => $unitPrice,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Recent activity of a product. Only reads the period, so it does not slow down as the
     * ledger grows, and the balance itself comes from the row rather than from here.
     *
     * @return array{purchased: string, sold: string, oversold: int, last: ?InventoryMovement}
     */
    public function summaryFor(BranchProduct $branchProduct, int $days): array
    {
        $totals = InventoryMovement::query()
            ->where('branch_product_id', $branchProduct->getKey())
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN quantity END), 0) as purchased', [MovementType::Purchase->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type IN (?, ?) THEN -quantity END), 0) as sold', [MovementType::Sale->value, MovementType::SaleCancellation->value])
            ->selectRaw('COUNT(CASE WHEN type = ? AND balance_after < 0 THEN 1 END) as oversold', [MovementType::Sale->value])
            ->toBase()
            ->first();

        return [
            'purchased' => bcadd((string) $totals->purchased, '0', 3),
            'sold' => bcadd((string) $totals->sold, '0', 3),
            'oversold' => (int) $totals->oversold,
            'last' => InventoryMovement::query()->where('branch_product_id', $branchProduct->getKey())->latest('id')->first(),
        ];
    }

    /**
     * What the branch's stock would sell for. Negative balances do not subtract from it.
     */
    public function valueAtSalePrice(Branch $branch): string
    {
        return (string) BranchProduct::query()
            ->forBranch($branch)
            ->where('stock', '>', 0)
            ->selectRaw('COALESCE(SUM(stock * price), 0) as value')
            ->toBase()
            ->value('value');
    }

    public function negativeStockCount(Branch $branch): int
    {
        return BranchProduct::query()->forBranch($branch)->where('stock', '<', 0)->count();
    }

    /**
     * Compare stored balances with the ledger.
     *
     * @return Collection<int, object{id: int, stock: string, ledger: string}>
     */
    public function discrepancies(): Collection
    {
        $ledger = InventoryMovement::query()
            ->selectRaw('branch_product_id, SUM(quantity) as total')
            ->groupBy('branch_product_id');

        return DB::table('branch_product')
            ->leftJoinSub($ledger, 'ledger', 'ledger.branch_product_id', '=', 'branch_product.id')
            ->whereRaw('branch_product.stock <> COALESCE(ledger.total, 0)')
            ->get(['branch_product.id', 'branch_product.stock', DB::raw('COALESCE(ledger.total, 0) as ledger')]);
    }
}
