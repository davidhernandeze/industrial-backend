<?php

namespace App\Filament\Resources\BranchSettings\Pages;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\BranchSettings\BranchSettingResource;
use App\Models\BranchSetting;
use Filament\Resources\Pages\ViewRecord;

class ViewBranchSetting extends ViewRecord
{
    protected static string $resource = BranchSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HistoryAction::make()
                ->iconButton()
                ->tooltip(__('history.label'))
                ->visible(fn (BranchSetting $record): bool => $record->currentValue !== null),
            BranchSettingResource::editAction(),
        ];
    }
}
