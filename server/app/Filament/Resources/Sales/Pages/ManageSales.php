<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Widgets\SalesOverview;
use Filament\Resources\Pages\ManageRecords;

class ManageSales extends ManageRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderWidgets(): array
    {
        return [SalesOverview::class];
    }
}
