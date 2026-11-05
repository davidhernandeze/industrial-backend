<?php

namespace App\Filament\Resources\BranchProducts;

use App\Enums\ProductUnit;
use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\BranchProducts\Pages\BranchProductHistory;
use App\Filament\Resources\BranchProducts\Pages\ManageBranchProducts;
use App\Filament\Resources\BranchProducts\Pages\ViewBranchProduct;
use App\Filament\Resources\Concerns\ScopedToCurrentBranch;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\RoleGuardedResource;
use App\Models\BranchProduct;
use App\Support\CurrentBranch;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchProductResource extends RoleGuardedResource
{
    use ScopedToCurrentBranch;

    protected static ?string $model = BranchProduct::class;

    protected static string|array $routeMiddleware = 'role:branch_manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'branch-products';

    public static function getModelLabel(): string
    {
        return __('branch_products.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('branch_products.plural_model_label');
    }

    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->product?->name;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label(__('branch_products.fields.product'))
                    ->relationship(
                        'product',
                        'name',
                        modifyQueryUsing: fn (Builder $query, string $operation): Builder => $operation === 'create'
                            ? $query->withoutTrashed()->notInBranch(app(CurrentBranch::class)->get())
                            : $query,
                    )
                    ->searchable(['name', 'sku', 'barcode'])
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $removed = BranchProduct::removedFromCurrentBranch($state);

                        if ($removed !== null) {
                            $set('unit', $removed->unit);
                        }
                    })
                    ->disabledOn('edit'),
                Select::make('unit')
                    ->label(__('branch_products.fields.unit'))
                    ->options(ProductUnit::class)
                    ->default(ProductUnit::Piece)
                    ->required()
                    ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit'
                        || BranchProduct::removedFromCurrentBranch($get('product_id')) !== null)
                    ->helperText(fn (Get $get, string $operation): ?string => $operation === 'create'
                        && BranchProduct::removedFromCurrentBranch($get('product_id')) !== null
                            ? __('branch_products.keeps_unit')
                            : null),
                TextInput::make('price')
                    ->label(__('branch_products.fields.price'))
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->step(0.01)
                    ->rule('decimal:0,2')
                    ->required(),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('product.picture_url')
                    ->label(__('products.fields.picture'))
                    ->placeholder('—'),
                TextEntry::make('product.name')
                    ->label(__('branch_products.fields.product')),
                TextEntry::make('product.barcode')
                    ->label(__('products.fields.barcode'))
                    ->placeholder('—'),
                TextEntry::make('unit')
                    ->label(__('branch_products.fields.unit')),
                TextEntry::make('price')
                    ->label(__('branch_products.fields.price'))
                    ->money('MXN', locale: 'es_MX'),
                TextEntry::make('stock')
                    ->label(__('branch_products.fields.stock'))
                    ->formatStateUsing(fn (BranchProduct $record): string => $record->unit->format($record->stock))
                    ->color(fn (BranchProduct $record): ?string => bccomp($record->stock, '0', 3) < 0 ? 'danger' : null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (BranchProduct $record): ?string => $record->product?->name)
            ->defaultSort('product.name')
            ->columns([
                ImageColumn::make('product.picture_url')
                    ->label(__('products.fields.picture')),
                TextColumn::make('product.name')
                    ->label(__('branch_products.fields.product'))
                    ->url(fn (BranchProduct $record): string => InventoryMovementResource::getUrlForProduct($record->product_id))
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.barcode')
                    ->label(__('products.fields.barcode'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('unit')
                    ->label(__('branch_products.fields.unit')),
                TextColumn::make('price')
                    ->label(__('branch_products.fields.price'))
                    ->money('MXN', locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label(__('branch_products.fields.stock'))
                    ->formatStateUsing(fn (BranchProduct $record): string => $record->unit->format($record->stock))
                    ->color(fn (BranchProduct $record): ?string => bccomp($record->stock, '0', 3) < 0 ? 'danger' : null)
                    ->sortable(),
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranchProducts::route('/'),
            'view' => ViewBranchProduct::route('/{record}'),
            'history' => BranchProductHistory::route('/{record}/history'),
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
