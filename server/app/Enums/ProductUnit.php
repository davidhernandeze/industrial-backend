<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductUnit: string implements HasLabel
{
    case Kilogram = 'kg';
    case Piece = 'piece';

    public function getLabel(): string
    {
        return __("branch_products.units.{$this->value}");
    }

    /**
     * Pieces are counted whole. Only weight can be fractional.
     */
    public function allowsFractions(): bool
    {
        return $this === self::Kilogram;
    }

    /**
     * A quantity for display, such as "1.5 kg" or "12 pza".
     */
    public function format(string $quantity): string
    {
        $number = str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;

        return $number.' '.__("branch_products.unit_abbreviations.{$this->value}");
    }
}
