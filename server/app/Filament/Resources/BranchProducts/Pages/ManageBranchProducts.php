<?php

namespace App\Filament\Resources\BranchProducts\Pages;

use App\Filament\Resources\BranchProducts\BranchProductResource;
use App\Models\BranchProduct;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBranchProducts extends ManageRecords
{
    protected static string $resource = BranchProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): BranchProduct => BranchProduct::addToCurrentBranch($data)),
        ];
    }
}
