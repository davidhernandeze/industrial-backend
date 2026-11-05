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
     * How much the quantity buttons change a line.
     */
    public function step(): string
    {
        return match ($this) {
            self::Kilogram => '0.25',
            self::Piece => '1',
        };
    }

    /**
     * The weights sold most often, for picking one without typing.
     *
     * @return array<string, string>
     */
    public function commonQuantities(): array
    {
        return match ($this) {
            self::Kilogram => [
                '0.1' => '100 g',
                '0.25' => '250 g',
                '0.5' => '500 g',
                '0.75' => '750 g',
                '1' => '1 kg',
                '1.5' => '1.5 kg',
                '2' => '2 kg',
            ],
            self::Piece => [],
        };
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
