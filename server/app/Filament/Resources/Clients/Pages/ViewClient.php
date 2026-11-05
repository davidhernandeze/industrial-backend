<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

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
