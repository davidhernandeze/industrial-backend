<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasColor, HasLabel
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case SaleCancellation = 'sale_cancellation';

    public function getLabel(): string
    {
        return __("inventory_movements.types.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase => 'success',
            self::Sale => 'info',
            self::Adjustment => 'warning',
            self::SaleCancellation => 'danger',
        };
    }

    /**
     * The types a person can enter by hand. Sales and their cancellations come from Ventas.
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        return collect([self::Purchase, self::Adjustment])
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->getLabel()])
            ->all();
    }

    /**
     * The quantity as stored: purchases add, sales subtract, adjustments keep their sign.
     */
    public function signedQuantity(string $quantity): string
    {
        $absolute = ltrim($quantity, '-');

        return match ($this) {
            self::Purchase, self::SaleCancellation => $absolute,
            self::Sale => "-{$absolute}",
            self::Adjustment => $quantity,
        };
    }
}
