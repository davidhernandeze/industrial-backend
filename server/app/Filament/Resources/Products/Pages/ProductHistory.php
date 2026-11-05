<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Pages\EntityHistory;
use App\Filament\Resources\Products\ProductResource;

class ProductHistory extends EntityHistory
{
    protected static string $resource = ProductResource::class;
}
