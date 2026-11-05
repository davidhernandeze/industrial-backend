<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Actions\CancelSaleAction;
use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HistoryAction::make()
                ->iconButton()
                ->tooltip(__('history.label')),
            CancelSaleAction::make(),
        ];
    }
}
