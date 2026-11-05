<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Enums\MovementType;
use App\Filament\Resources\Concerns\ScopedToCurrentBranch;
use App\Filament\Resources\InventoryMovements\Pages\ManageInventoryMovements;
use App\Filament\Resources\InventoryMovements\Pages\ViewInventoryMovement;
use App\Filament\Resources\RoleGuardedResource;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\CurrentBranch;
use BackedEnum;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The ledger of the current branch. Movements can be added and read, never changed, so this
 * is the one resource without edit, delete or history.
 */
class InventoryMovementResource extends RoleGuardedResource
{
    use ScopedToCurrentBranch;

    protected static ?string $model = InventoryMovement::class;

    protected static string|array $routeMiddleware = 'role:branch_manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'inventory';

    public static function getModelLabel(): string
    {
        return __('inventory_movements.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory_movements.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('inventory_movements.navigation_label');
    }

    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->branchProduct?->product?->name;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_product_id')
                    ->label(__('inventory_movements.fields.product'))
                    ->options(fn (): array => self::selectableProducts())
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::selectableProducts($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => BranchProduct::query()->find($value)?->product?->name)
                    ->live()
                    ->required(),
                Select::make('type')
                    ->label(__('inventory_movements.fields.type'))
                    ->options(MovementType::manualOptions())
                    ->live()
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('inventory_movements.fields.quantity'))
                    ->numeric()
                    ->required()
                    ->rule('decimal:0,3')
                    ->rule(fn (Get $get): Closure => self::quantityRule($get))
                    ->helperText(fn (Get $get): ?string => self::selectedType($get) === MovementType::Adjustment
                        ? __('inventory_movements.adjustment_help')
                        : null),
                TextInput::make('note')
                    ->label(__('inventory_movements.fields.note'))
                    ->maxLength(255),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('branchProduct.product.name')
                    ->label(__('inventory_movements.fields.product')),
                TextEntry::make('type')
                    ->label(__('inventory_movements.fields.type'))
                    ->badge(),
                TextEntry::make('quantity')
                    ->label(__('inventory_movements.fields.quantity'))
                    ->formatStateUsing(fn (InventoryMovement $record): string => self::signed($record)),
                TextEntry::make('balance_after')
                    ->label(__('inventory_movements.fields.balance_after'))
                    ->formatStateUsing(fn (InventoryMovement $record): string => $record->branchProduct->unit->format($record->balance_after))
                    ->color(fn (InventoryMovement $record): ?string => $record->isOversold() ? 'danger' : null),
                TextEntry::make('oversold')
                    ->label(__('inventory_movements.fields.flag'))
                    ->state(fn (InventoryMovement $record): ?string => $record->isOversold() ? __('inventory_movements.oversold') : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
                TextEntry::make('user.name')
                    ->label(__('inventory_movements.fields.user'))
                    ->placeholder(__('history.system')),
                TextEntry::make('note')
                    ->label(__('inventory_movements.fields.note'))
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label(__('inventory_movements.fields.date'))
                    ->dateTime('d/m/Y H:i:s'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (InventoryMovement $record): ?string => $record->branchProduct?->product?->name)
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('inventory_movements.fields.date'))
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('branchProduct.product.name')
                    ->label(__('inventory_movements.fields.product'))
                    ->url(fn (InventoryMovement $record): string => self::getUrlForProduct($record->branchProduct->product_id))
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('branchProduct.product.barcode')
                    ->label(__('products.fields.barcode'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label(__('inventory_movements.fields.type'))
                    ->badge(),
                TextColumn::make('quantity')
                    ->label(__('inventory_movements.fields.quantity'))
                    ->formatStateUsing(fn (InventoryMovement $record): string => self::signed($record)),
                TextColumn::make('balance_after')
                    ->label(__('inventory_movements.fields.balance_after'))
                    ->formatStateUsing(fn (InventoryMovement $record): string => $record->branchProduct->unit->format($record->balance_after))
                    ->color(fn (InventoryMovement $record): ?string => $record->isOversold() ? 'danger' : null),
                TextColumn::make('oversold')
                    ->label(__('inventory_movements.fields.flag'))
                    ->state(fn (InventoryMovement $record): ?string => $record->isOversold() ? __('inventory_movements.oversold') : null)
                    ->badge()
                    ->color('danger'),
                TextColumn::make('user.name')
                    ->label(__('inventory_movements.fields.user'))
                    ->placeholder(__('history.system')),
            ])
            ->filters([
                SelectFilter::make('product')
                    ->label(__('inventory_movements.fields.product'))
                    ->options(fn (): array => self::productsWithMovements())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $productId): Builder => $query->whereHas(
                            'branchProduct',
                            fn (Builder $rows) => $rows->where('product_id', $productId),
                        ),
                    )),
                SelectFilter::make('type')
                    ->label(__('inventory_movements.fields.type'))
                    ->options(MovementType::class),
                Filter::make('oversold')
                    ->label(__('inventory_movements.oversold'))
                    ->query(fn (Builder $query): Builder => $query->oversold()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventoryMovements::route('/'),
            'view' => ViewInventoryMovement::route('/{record}'),
        ];
    }

    /**
     * This table, filtered to one product of the current branch.
     */
    public static function getUrlForProduct(int $productId): string
    {
        return static::getUrl('index', ['filters' => ['product' => ['value' => $productId]]]);
    }

    /**
     * The current branch's row for the product a table is filtered to, if any.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function filteredBranchProduct(?array $filters): ?BranchProduct
    {
        $productId = $filters['product']['value'] ?? null;

        if (blank($productId)) {
            return null;
        }

        return BranchProduct::withTrashed()
            ->forBranch(app(CurrentBranch::class)->get())
            ->where('product_id', $productId)
            ->with('product')
            ->first();
    }

    /**
     * Products the current branch has offered, keyed by product id. Removed and discontinued
     * products stay listed because their movements remain.
     *
     * @return array<int, string>
     */
    private static function productsWithMovements(): array
    {
        return Product::withTrashed()
            ->whereHas('branchProducts', fn (Builder $rows) => $rows
                ->withTrashed()
                ->forBranch(app(CurrentBranch::class)->get()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The current branch's products as dropdown options, found by name, SKU or barcode.
     *
     * @return array<int, string>
     */
    private static function selectableProducts(?string $search = null): array
    {
        /** @var Collection<int, BranchProduct> $rows */
        $rows = BranchProduct::query()
            ->forBranch(app(CurrentBranch::class)->get())
            ->whereHas('product', fn (Builder $products) => $products
                ->withoutTrashed()
                ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%"))))
            ->with('product')
            ->limit(50)
            ->get();

        return $rows->mapWithKeys(fn (BranchProduct $row): array => [$row->id => $row->product->name])->all();
    }

    private static function quantityRule(Get $get): Closure
    {
        $type = self::selectedType($get);
        $unit = BranchProduct::query()->find($get('branch_product_id'))?->unit;

        return function (string $attribute, mixed $value, Closure $fail) use ($type, $unit): void {
            $quantity = (string) $value;

            if (bccomp($quantity, '0', 3) === 0) {
                $fail(__('inventory_movements.errors.zero'));
            } elseif ($type !== MovementType::Adjustment && bccomp($quantity, '0', 3) < 0) {
                $fail(__('inventory_movements.errors.negative'));
            } elseif ($unit !== null && ! $unit->allowsFractions() && bccomp($quantity, bcadd($quantity, '0', 0), 3) !== 0) {
                $fail(__('inventory_movements.errors.whole'));
            }
        };
    }

    private static function selectedType(Get $get): ?MovementType
    {
        $type = $get('type');

        return $type instanceof MovementType ? $type : MovementType::tryFrom((string) $type);
    }

    private static function signed(InventoryMovement $record): string
    {
        $prefix = bccomp($record->quantity, '0', 3) > 0 ? '+' : '';

        return $prefix.$record->branchProduct->unit->format($record->quantity);
    }
}
