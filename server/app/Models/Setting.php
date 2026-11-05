<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use Auditable, HasFactory;

    /**
     * Get the stored value of a setting, memoized for the current request.
     */
    public static function valueOf(string $key): ?string
    {
        return once(fn (): ?string => static::query()->where('key', $key)->value('value'));
    }

    /**
     * The translated name of the setting, or its key when no translation exists.
     */
    protected function label(): Attribute
    {
        return Attribute::get(function (): string {
            $translationKey = "settings.keys.{$this->key}";

            return Lang::has($translationKey) ? __($translationKey) : $this->key;
        });
    }
}
