<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin';
    case BranchManager = 'branch_manager';
    case Cashier = 'cashier';
    case Driver = 'driver';

    public function getLabel(): string
    {
        return __("users.roles.{$this->value}");
    }
}
