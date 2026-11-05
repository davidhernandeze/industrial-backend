<?php

namespace App\Filament\Resources\InventoryMovements\Widgets;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\BranchProduct;
use App\Services\Inventory;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

/**
 * Key figures for the product the inventory table is filtered to.
 */
class ProductInventoryOverview extends StatsOverviewWidget
{
    public const string MOVEMENT_RECORDED = 'inventory-movement-recorded';

    private const int PERIOD_DAYS = 30;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $tableFilters = null;

    /**
     * Recording a movement leaves the filter unchanged, so the figures need an explicit
     * refresh or they would disagree with the table below them.
     */
    #[On(self::MOVEMENT_RECORDED)]
    public function refreshFigures(): void {}

    protected function getHeading(): ?string
    {
        return $this->row()?->product?->name;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $row = $this->row();

        if ($row === null) {
            return [];
        }

        $summary = app(Inventory::class)->summaryFor($row, self::PERIOD_DAYS);
        $period = __('inventory_movements.overview.period', ['days' => self::PERIOD_DAYS]);

        return [
            $this->stock($row),
            Stat::make(__('branch_products.fields.price'), $this->money($row->price))
                ->description(__('inventory_movements.overview.per_unit', ['unit' => mb_strtolower($row->unit->getLabel())])),
            Stat::make(__('inventory_movements.overview.sold'), $row->unit->format($summary['sold']))
                ->description($period),
            Stat::make(__('inventory_movements.overview.purchased'), $row->unit->format($summary['purchased']))
                ->description($period),
            Stat::make(__('inventory_movements.overview.oversold'), (string) $summary['oversold'])
                ->description($period)
                ->color($summary['oversold'] > 0 ? 'danger' : 'gray'),
            Stat::make(__('inventory_movements.overview.last_movement'), $summary['last']?->created_at->format('d/m/Y H:i') ?? '—')
                ->description($summary['last']?->type->getLabel()),
        ];
    }

    private function stock(BranchProduct $row): Stat
    {
        $stat = Stat::make(__('branch_products.fields.stock'), $row->unit->format($row->stock));

        if (bccomp($row->stock, '0', 3) < 0) {
            return $stat
                ->description(__('inventory_movements.overview.negative_stock'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger');
        }

        return $stat->description(__('inventory_movements.overview.value', [
            'value' => $this->money(bcmul($row->stock, $row->price, 2)),
        ]));
    }

    private function money(string $amount): string
    {
        return Number::currency((float) $amount, 'MXN', 'es_MX');
    }

    private function row(): ?BranchProduct
    {
        return InventoryMovementResource::filteredBranchProduct($this->tableFilters);
    }
}
