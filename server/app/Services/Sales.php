<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\SaleOutOfStock;
use App\Support\CurrentBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Creates and cancels sales. Totals and prices always come from the database, never from
 * the client, and a sale and its movements commit together.
 */
class Sales
{
    public function __construct(private Inventory $inventory) {}

    /**
     * Payment is assumed to cover the sale in full, so it is created as paid. Submitting the
     * same uuid twice returns the first sale instead of charging again.
     *
     * @param  array<int, string>  $lines  Quantities keyed by branch_product id
     */
    public function create(
        string $uuid,
        array $lines,
        PaymentMethod $method,
        ?string $amountReceived = null,
        ?string $reference = null,
    ): Sale {
        if ($lines === []) {
            throw new SaleRejected(__('sales.errors.empty'));
        }

        $sale = DB::transaction(function () use ($uuid, $lines, $method, $amountReceived, $reference): Sale {
            $branch = Branch::query()->lockForUpdate()->findOrFail(app(CurrentBranch::class)->get()?->getKey());

            $existing = Sale::query()->where('uuid', $uuid)->first();

            if ($existing !== null) {
                return $existing;
            }

            $rows = $this->rowsFor($branch, $lines);
            $total = $rows->reduce(
                fn (string $sum, BranchProduct $row): string => bcadd($sum, self::lineTotal($row->price, $lines[$row->id]), 2),
                '0.00',
            );

            if ($amountReceived !== null && bccomp($amountReceived, $total, 2) < 0) {
                throw new SaleRejected(__('sales.errors.insufficient_payment'));
            }

            $sale = new Sale([
                'uuid' => $uuid,
                'folio' => (int) Sale::withTrashed()->where('branch_id', $branch->id)->max('folio') + 1,
                'user_id' => auth()->id(),
                'status' => SaleStatus::Paid,
                'type' => SaleType::PointOfSale,
                'total' => $total,
            ]);
            $sale->branch_id = $branch->id;
            $sale->save();

            $sale->payments()->create([
                'method' => $method,
                'amount' => $total,
                'amount_received' => $amountReceived,
                'reference' => $reference,
            ]);

            // Ascending ids: two sales locking the same rows cannot deadlock each other.
            foreach ($rows->sortBy('id') as $row) {
                $this->inventory->record($row, MovementType::Sale, $lines[$row->id], reference: $sale, unitPrice: $row->price);
            }

            return $sale;
        });

        if ($sale->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->notifyIfOutOfStock($sale));
        }

        return $sale;
    }

    /**
     * Stock returns through new movements: the ledger is never edited.
     */
    public function cancel(Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $reason): Sale {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if ($locked->isCancelled()) {
                throw new SaleRejected(__('sales.errors.already_cancelled'));
            }

            $locked->update([
                'status' => SaleStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $reason,
            ]);

            foreach ($locked->lines()->with('branchProduct')->orderBy('branch_product_id')->get() as $line) {
                $this->inventory->record(
                    $line->branchProduct,
                    MovementType::SaleCancellation,
                    ltrim($line->quantity, '-'),
                    reference: $locked,
                    unitPrice: $line->unit_price,
                );
            }

            return $locked;
        });
    }

    /**
     * A price times a quantity, rounded half up to cents.
     */
    public static function lineTotal(string $price, string $quantity): string
    {
        return bcadd(bcmul($price, $quantity, 5), '0.005', 2);
    }

    /**
     * @param  array<int, string>  $lines
     * @return Collection<int, BranchProduct>
     */
    private function rowsFor(Branch $branch, array $lines): Collection
    {
        $rows = BranchProduct::query()
            ->forBranch($branch)
            ->whereKey(array_keys($lines))
            ->whereHas('product', fn (Builder $products) => $products->withoutTrashed())
            ->with('product')
            ->get();

        if ($rows->count() !== count($lines)) {
            throw new SaleRejected(__('sales.errors.unavailable_product'));
        }

        return $rows;
    }

    /**
     * A single notification per sale, only for products this sale took below zero. Later sales
     * of a product that is already negative stay flagged but send nothing.
     */
    private function notifyIfOutOfStock(Sale $sale): void
    {
        $lines = $sale->lines()->get()->filter(fn (InventoryMovement $line): bool => $line->wentNegative());

        if ($lines->isEmpty()) {
            return;
        }

        rescue(fn () => Notification::send(
            $this->recipientsFor($sale),
            new SaleOutOfStock($sale, $lines->pluck('id')->all()),
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(Sale $sale): Collection
    {
        return User::query()
            ->where('role', Role::Admin)
            ->orWhere(fn (Builder $managers) => $managers
                ->where('role', Role::BranchManager)
                ->forBranch($sale->branch_id))
            ->get();
    }
}
