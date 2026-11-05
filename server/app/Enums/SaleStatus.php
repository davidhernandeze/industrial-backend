<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SaleStatus: string implements HasColor, HasLabel
{
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("sales.statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}
