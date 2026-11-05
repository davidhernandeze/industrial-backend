<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Notifications\SaleOutOfStock;
use App\Support\CurrentBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Creates and cancels sales. Totals and prices always come from the database, never from
 * the client, and a sale and its movements commit together.
 */
class Sales
{
    private const int BEST_SELLERS_CACHE_MINUTES = 10;

    /**
     * Extra ranked products kept in the cache, in case some leave the branch before it expires.
     */
    private const int BEST_SELLERS_SPARE = 3;

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
        ?int $clientId = null,
    ): Sale {
        if ($lines === []) {
            throw new SaleRejected(__('sales.errors.empty'));
        }

        $sale = DB::transaction(function () use ($uuid, $lines, $method, $amountReceived, $reference, $clientId): Sale {
            $branch = Branch::query()->lockForUpdate()->findOrFail(app(CurrentBranch::class)->get()?->getKey());

            $existing = Sale::query()->where('uuid', $uuid)->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($clientId !== null && ! Client::query()->whereKey($clientId)->exists()) {
                throw new SaleRejected(__('sales.errors.unavailable_client'));
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
                'client_id' => $clientId,
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
     * Today's and this month's figures. Each query reads only its date window, so this does
     * not slow down as the sales history grows.
     *
     * @return array{total: string, count: int, average: string, cancelled: int, month_total: string, by_method: array<string, string>}
     */
    public function overview(Branch $branch): array
    {
        $today = now()->startOfDay();

        $sold = Sale::query()
            ->forBranch($branch)
            ->where('status', SaleStatus::Paid)
            ->where('created_at', '>=', $today)
            ->toBase()
            ->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as sales')
            ->first();

        return [
            'total' => (string) $sold->total,
            'count' => (int) $sold->sales,
            'average' => (int) $sold->sales === 0 ? '0' : bcdiv((string) $sold->total, (string) $sold->sales, 2),
            'cancelled' => Sale::query()->forBranch($branch)->where('cancelled_at', '>=', $today)->count(),
            'month_total' => (string) Sale::query()
                ->forBranch($branch)
                ->where('status', SaleStatus::Paid)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total'),
            'by_method' => SalePayment::query()
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->where('sales.branch_id', $branch->id)
                ->where('sales.status', SaleStatus::Paid)
                ->where('sales.created_at', '>=', $today)
                ->groupBy('sale_payments.method')
                ->selectRaw('sale_payments.method as method, SUM(sale_payments.amount) as amount')
                ->toBase()
                ->pluck('amount', 'method')
                ->map(fn (mixed $amount): string => (string) $amount)
                ->all(),
        ];
    }

    /**
     * Paid sales per day, oldest first and including days without sales.
     *
     * @return Collection<string, array{total: string, count: int}>
     */
    public function dailyTotals(Branch $branch, int $days): Collection
    {
        $rows = Sale::query()
            ->forBranch($branch)
            ->where('status', SaleStatus::Paid)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->toBase()
            ->selectRaw('DATE(created_at) as day, SUM(total) as total, COUNT(*) as sales')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(range($days - 1, 0))->mapWithKeys(function (int $daysAgo) use ($rows): array {
            $day = now()->subDays($daysAgo)->toDateString();

            return [$day => [
                'total' => (string) ($rows[$day]->total ?? '0'),
                'count' => (int) ($rows[$day]->sales ?? 0),
            ]];
        });
    }

    /**
     * The branch's most sold products of the last days, net of cancellations. The ranking is
     * cached briefly because the till reads it constantly. Prices and availability are live.
     *
     * @return Collection<int, BranchProduct>
     */
    public function bestSellers(Branch $branch, int $limit = 5, int $days = 30): Collection
    {
        $ids = Cache::remember(
            "sales:best-sellers:{$branch->id}:{$limit}:{$days}",
            now()->addMinutes(self::BEST_SELLERS_CACHE_MINUTES),
            fn (): array => InventoryMovement::query()
                ->where('branch_id', $branch->id)
                ->where('created_at', '>=', now()->subDays($days))
                ->whereIn('type', [MovementType::Sale, MovementType::SaleCancellation])
                ->groupBy('branch_product_id')
                ->havingRaw('SUM(quantity) < 0')
                ->orderByRaw('SUM(quantity)')
                ->limit($limit * self::BEST_SELLERS_SPARE)
                ->pluck('branch_product_id')
                ->all(),
        );

        $rows = BranchProduct::query()
            ->whereKey($ids)
            ->whereHas('product', fn (Builder $products) => $products->withoutTrashed())
            ->with('product')
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->filter(fn (int $id): bool => $rows->has($id))
            ->take($limit)
            ->map(fn (int $id): BranchProduct => $rows[$id])
            ->values();
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

        foreach ($lines as $quantity) {
            if (bccomp($quantity, '0', 3) <= 0) {
                throw new SaleRejected(__('point_of_sale.invalid_quantity'));
            }
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
