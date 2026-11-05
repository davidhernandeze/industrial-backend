<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\RoleGuardedResource;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Resources\Settings\Pages\SettingHistory;
use App\Filament\Resources\Settings\Pages\ViewSetting;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class SettingResource extends RoleGuardedResource
{
    protected static ?string $model = Setting::class;

    protected static string|array $routeMiddleware = 'role:admin';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('settings.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.plural_model_label');
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
            ->components([
                Textarea::make('value')
                    ->label(fn (Setting $record): string => $record->label)
                    ->rows(3)
                    ->maxLength(65535),
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
                TextEntry::make('value')
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
                TextColumn::make('value')
                    ->label(__('settings.fields.value'))
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label(__('settings.fields.updated_at'))
                    ->since(),
            ])
            ->recordActions([
                ActionGroup::make([
                    HistoryAction::make(),
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSettings::route('/'),
            'view' => ViewSetting::route('/{record}'),
            'history' => SettingHistory::route('/{record}/history'),
        ];
    }
}
