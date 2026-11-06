<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Lang;

/**
 * For settings, named by a translation under "{table}.keys".
 */
trait LabeledByKey
{
    /**
     * The translated name of the setting, or its key when no translation exists.
     */
    protected function label(): Attribute
    {
        return Attribute::get(function (): string {
            $translationKey = "{$this->getTable()}.keys.{$this->key}";

            return Lang::has($translationKey) ? __($translationKey) : $this->key;
        });
    }
}
