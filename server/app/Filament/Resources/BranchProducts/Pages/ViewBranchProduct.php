<?php

namespace App\Filament\Resources\BranchProducts\Pages;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\BranchProducts\BranchProductResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBranchProduct extends ViewRecord
{
    protected static string $resource = BranchProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HistoryAction::make()
                ->iconButton()
                ->tooltip(__('history.label')),
            EditAction::make(),
        ];
    }
}
