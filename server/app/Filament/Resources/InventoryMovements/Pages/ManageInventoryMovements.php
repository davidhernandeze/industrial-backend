<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Enums\MovementType;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\InventoryMovements\Widgets\ProductInventoryOverview;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Services\Inventory;
use App\Support\CurrentBranch;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInventoryMovements extends ManageRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderWidgets(): array
    {
        return InventoryMovementResource::filteredBranchProduct($this->tableFilters) === null
            ? []
            : [ProductInventoryOverview::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['tableFilters' => $this->tableFilters];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): InventoryMovement => app(Inventory::class)->record(
                    BranchProduct::query()->forBranch(app(CurrentBranch::class)->get())->findOrFail($data['branch_product_id']),
                    $data['type'] instanceof MovementType ? $data['type'] : MovementType::from($data['type']),
                    (string) $data['quantity'],
                    $data['note'] ?? null,
                ))
                ->after(fn () => $this->dispatch(ProductInventoryOverview::MOVEMENT_RECORDED)),
        ];
    }
}
