<?php

namespace App\Models\Concerns;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * For rows that store a setting's value in value and an entity relation.
 */
trait HasTypedValue
{
    abstract public function settingType(): SettingType;

    /**
     * The value as business logic should use it.
     */
    protected function parsedValue(): Attribute
    {
        return Attribute::get(fn (): mixed => $this->settingType()->parse($this->value, $this->entity));
    }

    protected function displayValue(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->settingType()->format($this->value, $this->entity));
    }
}
