<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * How a setting's stored value is read. Every type can be empty, which reads as null.
 */
enum SettingType: string implements HasLabel
{
    case String = 'string';
    case Number = 'number';
    case Price = 'price';
    case Entity = 'entity';

    public function getLabel(): string
    {
        return __("settings.types.{$this->value}");
    }

    /**
     * The value for business logic: a string, an int or float, a price as a string with two
     * decimals like every other money column, or the related model.
     */
    public function parse(?string $value, ?Model $entity = null): Model|string|int|float|null
    {
        if ($this === self::Entity) {
            return $entity;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($this) {
            self::Price => bcadd($value, '0', 2),
            self::Number => $value + 0,
            default => $value,
        };
    }

    /**
     * The value for display.
     */
    public function format(?string $value, ?Model $entity = null): ?string
    {
        $parsed = $this->parse($value, $entity);

        return match (true) {
            $parsed === null => null,
            $this === self::Price => Number::currency((float) $parsed, 'MXN', 'es_MX'),
            $this === self::Entity => $parsed->name,
            default => (string) $parsed,
        };
    }
}
