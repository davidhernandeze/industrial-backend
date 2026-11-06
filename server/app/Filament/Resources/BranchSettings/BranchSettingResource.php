<?php

namespace App\Filament\Resources\BranchSettings;

use App\Filament\Actions\HistoryAction;
use App\Filament\Concerns\ShownInBranchSection;
use App\Filament\Forms\SettingValueField;
use App\Filament\Resources\BranchSettings\Pages\BranchSettingHistory;
use App\Filament\Resources\BranchSettings\Pages\ManageBranchSettings;
use App\Filament\Resources\BranchSettings\Pages\ViewBranchSetting;
use App\Filament\Resources\RoleGuardedResource;
use App\Models\BranchSetting;
use App\Support\CurrentBranch;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lists every branch setting, whether or not the current branch has set it, with that
 * branch's value. Settings are shared, so unlike other branch modules nothing is filtered.
 */
class BranchSettingResource extends RoleGuardedResource
{
    use ShownInBranchSection;

    protected static ?string $model = BranchSetting::class;

    protected static string|array $routeMiddleware = 'role:branch_manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'branch-settings';

    public static function getModelLabel(): string
    {
        return __('branch_settings.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('branch_settings.plural_model_label');
    }

    /**
     * Spanish capitalizes only the first word.
     */
    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function canAccess(): bool
    {
        return app(CurrentBranch::class)->get() !== null && parent::canAccess();
    }

    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->label;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(fn (BranchSetting $record): array => [
                SettingValueField::make($record->type, $record->entity_type, $record->label),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('label')
                    ->label(__('settings.fields.name')),
                TextEntry::make('key')
                    ->label(__('settings.fields.key')),
                TextEntry::make('type')
                    ->label(__('settings.fields.type')),
                TextEntry::make('currentValue.display_value')
                    ->label(__('settings.fields.value'))
                    ->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('key')
            ->columns([
                TextColumn::make('label')
                    ->label(__('settings.fields.name')),
                TextColumn::make('currentValue.display_value')
                    ->label(__('settings.fields.value'))
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('currentValue.updated_at')
                    ->label(__('settings.fields.updated_at'))
                    ->since()
                    ->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    HistoryAction::make()
                        ->visible(fn (BranchSetting $record): bool => $record->currentValue !== null),
                    ViewAction::make(),
                    static::editAction(),
                ]),
            ]);
    }

    /**
     * Edits the current branch's value, not the setting itself.
     */
    public static function editAction(): EditAction
    {
        return EditAction::make()
            ->fillForm(fn (BranchSetting $record): array => $record->currentValue?->only(['value', 'entity_id']) ?? [])
            ->using(function (BranchSetting $record, array $data): BranchSetting {
                $record->saveValueFor(app(CurrentBranch::class)->get(), $data);

                return $record;
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranchSettings::route('/'),
            'view' => ViewBranchSetting::route('/{record}'),
            'history' => BranchSettingHistory::route('/{record}/history'),
        ];
    }
}
