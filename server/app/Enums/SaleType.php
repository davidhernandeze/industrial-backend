<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SaleType: string implements HasLabel
{
    case PointOfSale = 'point_of_sale';

    public function getLabel(): string
    {
        return __("sales.types.{$this->value}");
    }
}
