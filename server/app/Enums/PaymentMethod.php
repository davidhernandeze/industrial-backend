<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return __("sales.payment_methods.{$this->value}");
    }
}
