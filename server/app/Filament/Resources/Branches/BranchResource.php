<?php

namespace App\Filament\Resources\Branches;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\Branches\Pages\BranchHistory;
use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Filament\Resources\Branches\Pages\ViewBranch;
use App\Filament\Resources\RoleGuardedResource;
use App\Models\Branch;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends RoleGuardedResource
{
    protected static ?string $model = Branch::class;

    protected static string|array $routeMiddleware = 'role:admin';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('branches.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('branches.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('branches.fields.name'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('address')
                    ->label(__('branches.fields.address'))
                    ->rows(3)
                    ->maxLength(65535),
                TextInput::make('google_maps_url')
                    ->label(__('branches.fields.google_maps_url'))
                    ->url()
                    ->maxLength(2048),
                DatePicker::make('opening_date')
                    ->label(__('branches.fields.opening_date')),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('branches.fields.name')),
                TextEntry::make('address')
                    ->label(__('branches.fields.address'))
                    ->placeholder('—'),
                TextEntry::make('google_maps_url')
                    ->label(__('branches.fields.map'))
                    ->formatStateUsing(fn (): string => __('branches.view_map'))
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('—'),
                TextEntry::make('opening_date')
                    ->label(__('branches.fields.opening_date'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('branches.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label(__('branches.fields.address'))
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('google_maps_url')
                    ->label(__('branches.fields.map'))
                    ->formatStateUsing(fn (): string => __('branches.view_map'))
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('opening_date')
                    ->label(__('branches.fields.opening_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    HistoryAction::make(),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranches::route('/'),
            'view' => ViewBranch::route('/{record}'),
            'history' => BranchHistory::route('/{record}/history'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
