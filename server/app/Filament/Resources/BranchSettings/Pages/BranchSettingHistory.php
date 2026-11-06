<?php

namespace App\Filament\Resources\BranchSettings\Pages;

use App\Filament\Resources\BranchSettings\BranchSettingResource;
use App\Filament\Resources\Pages\EntityHistory;
use Illuminate\Database\Eloquent\Model;

/**
 * The changes to the current branch's value. A branch never sees another branch's history.
 */
class BranchSettingHistory extends EntityHistory
{
    protected static string $resource = BranchSettingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_if($this->getRecord()->currentValue === null, 404);
    }

    protected function subject(): Model
    {
        return $this->getRecord()->currentValue;
    }
}
