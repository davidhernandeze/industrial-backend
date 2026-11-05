<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\Settings\SettingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSetting extends ViewRecord
{
    protected static string $resource = SettingResource::class;

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
