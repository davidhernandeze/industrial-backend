<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTypedValue;
use App\Models\Concerns\LabeledByKey;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['key', 'type', 'value', 'entity_type', 'entity_id'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use Auditable, HasFactory, HasTypedValue, LabeledByKey;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'type' => 'string',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }

    /**
     * Get the value of a setting parsed by its type, memoized for the current request.
     */
    public static function valueOf(string $key): mixed
    {
        return once(fn (): mixed => static::query()->where('key', $key)->first()?->parsed_value);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function settingType(): SettingType
    {
        return $this->type;
    }
}
