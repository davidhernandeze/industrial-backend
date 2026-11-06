<?php

namespace App\Filament\Forms;

use App\Enums\SettingType;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

/**
 * The input for a setting's value. Every type can be left empty.
 */
class SettingValueField
{
    private const int SEARCH_LIMIT = 50;

    /**
     * @param  class-string|null  $entityType  A model with a name attribute.
     */
    public static function make(SettingType $type, ?string $entityType, string $label): Field
    {
        $field = match ($type) {
            SettingType::String => Textarea::make('value')
                ->rows(3)
                ->maxLength(65535),
            SettingType::Number => TextInput::make('value')
                ->numeric(),
            SettingType::Price => TextInput::make('value')
                ->numeric()
                ->prefix('$')
                ->minValue(0)
                ->maxValue(99999999.99)
                ->step(0.01)
                ->rule('decimal:0,2'),
            SettingType::Entity => Select::make('entity_id')
                ->searchable()
                ->options(fn (): array => self::entityOptions($entityType))
                ->getSearchResultsUsing(fn (string $search): array => self::entityOptions($entityType, $search))
                ->getOptionLabelUsing(fn (mixed $value): ?string => $entityType::query()->find($value)?->name),
        };

        return $field->label($label);
    }

    /**
     * The first records by name, listed when the field opens and narrowed by searching.
     *
     * @param  class-string  $entityType
     * @return array<int, string>
     */
    private static function entityOptions(string $entityType, ?string $search = null): array
    {
        return $entityType::query()
            ->when($search, fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->pluck('name', 'id')
            ->all();
    }
}
