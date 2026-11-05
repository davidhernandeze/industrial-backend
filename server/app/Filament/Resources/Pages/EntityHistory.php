<?php

namespace App\Filament\Resources\Pages;

use BackedEnum;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * The change history of a single record, from its entity logs.
 *
 * Extend it once per resource, register it as the resource's "history" page and add
 * HistoryAction to the resource's table.
 */
abstract class EntityHistory extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return __('history.title', [
            'record' => static::getResource()::getRecordTitle($this->getRecord()),
        ]);
    }

    public function getBreadcrumb(): string
    {
        return __('history.label');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Activity::query()
                ->whereMorphedTo('subject', $this->getRecord())
                ->with(['causer' => fn (MorphTo $causer) => $causer->withTrashed()]))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('history.empty'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('history.fields.date'))
                    ->dateTime('d/m/Y H:i:s'),
                TextColumn::make('event')
                    ->label(__('history.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("history.events.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('causer_name')
                    ->label(__('history.fields.user'))
                    ->state(fn (Activity $activity): string => $activity->causer?->name ?? __('history.system')),
                TextColumn::make('changes')
                    ->label(__('history.fields.changes'))
                    ->state(fn (Activity $activity): array => $this->describeChanges($activity))
                    ->listWithLineBreaks()
                    ->wrap()
                    ->placeholder('—'),
            ]);
    }

    /**
     * One readable line per changed attribute. Deleting and restoring change nothing else.
     *
     * @return list<string>
     */
    private function describeChanges(Activity $activity): array
    {
        if (! in_array($activity->event, ['created', 'updated'], true)) {
            return [];
        }

        $old = $activity->attribute_changes['old'] ?? [];

        return collect($activity->attribute_changes['attributes'] ?? [])
            ->reject(fn (mixed $value, string $attribute): bool => $value === null && ! array_key_exists($attribute, $old))
            ->map(function (mixed $value, string $attribute) use ($old): string {
                $change = array_key_exists($attribute, $old)
                    ? $this->formatValue($attribute, $old[$attribute]).' → '.$this->formatValue($attribute, $value)
                    : $this->formatValue($attribute, $value);

                return "{$this->attributeLabel($attribute)}: {$change}";
            })
            ->values()
            ->all();
    }

    private function formatValue(string $attribute, mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            $this->relatedName($attribute, $value) !== null => $this->relatedName($attribute, $value),
            $this->enumCast($attribute) !== null => $this->enumCast($attribute)::from($value)->getLabel(),
            $this->getRecord()->hasCast($attribute, ['date', 'custom_datetime']) => Date::parse($value)->format('d/m/Y'),
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * The name behind a foreign key, so the log reads "Producto: Leche" and not an id.
     */
    private function relatedName(string $attribute, mixed $id): ?string
    {
        $relation = Str::camel(Str::beforeLast($attribute, '_id'));

        if (! str_ends_with($attribute, '_id') || ! method_exists($this->getRecord(), $relation)) {
            return null;
        }

        return $this->getRecord()->{$relation}()->getRelated()->newQueryWithoutScopes()->find($id)?->name;
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    private function enumCast(string $attribute): ?string
    {
        $cast = $this->getRecord()->getCasts()[$attribute] ?? null;

        return is_string($cast) && enum_exists($cast) ? $cast : null;
    }

    private function attributeLabel(string $attribute): string
    {
        $translationFile = Str::snake(Str::pluralStudly(class_basename($this->getRecord())));
        $translationKey = "{$translationFile}.fields.{$attribute}";

        return Lang::has($translationKey) ? __($translationKey) : Str::headline($attribute);
    }
}
