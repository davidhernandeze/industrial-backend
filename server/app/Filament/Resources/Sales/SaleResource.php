<?php

namespace App\Filament\Resources\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Filament\Actions\CancelSaleAction;
use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\Concerns\ScopedToCurrentBranch;
use App\Filament\Resources\RoleGuardedResource;
use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Filament\Resources\Sales\Pages\SaleHistory;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Sales;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SaleResource extends RoleGuardedResource
{
    use ScopedToCurrentBranch;

    protected static ?string $model = Sale::class;

    protected static string|array $routeMiddleware = 'role:branch_manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('sales.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sales.plural_model_label');
    }

    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record === null ? null : "#{$record->folio}";
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('folio')->label(__('sales.fields.folio'))->prefix('#'),
                        TextEntry::make('created_at')->label(__('sales.fields.date'))->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('user.name')->label(__('sales.fields.user')),
                        TextEntry::make('client.name')->label(__('sales.fields.client'))->placeholder('—'),
                        TextEntry::make('status')->label(__('sales.fields.status'))->badge(),
                        TextEntry::make('type')->label(__('sales.fields.type')),
                        TextEntry::make('total')->label(__('sales.fields.total'))->money('MXN', locale: 'es_MX'),
                    ]),
                Section::make(__('sales.sections.lines'))
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('branchProduct.product.name')->label(__('sales.fields.product')),
                                TextEntry::make('quantity')
                                    ->label(__('sales.fields.quantity'))
                                    ->formatStateUsing(fn (InventoryMovement $record): string => $record->branchProduct->unit->format(ltrim($record->quantity, '-'))),
                                TextEntry::make('unit_price')->label(__('sales.fields.unit_price'))->money('MXN', locale: 'es_MX'),
                                TextEntry::make('line_total')
                                    ->label(__('sales.fields.line_total'))
                                    ->state(fn (InventoryMovement $record): string => Sales::lineTotal($record->unit_price, ltrim($record->quantity, '-')))
                                    ->money('MXN', locale: 'es_MX'),
                            ]),
                    ]),
                Section::make(__('sales.sections.payments'))
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('method')->label(__('sales.fields.payment_method')),
                                TextEntry::make('amount')->label(__('sales.fields.total'))->money('MXN', locale: 'es_MX'),
                                TextEntry::make('amount_received')
                                    ->label(__('sales.fields.amount_received'))
                                    ->money('MXN', locale: 'es_MX')
                                    ->placeholder('—'),
                                TextEntry::make('details')
                                    ->label(__('sales.fields.change'))
                                    ->state(fn (SalePayment $record): ?string => $record->change)
                                    ->money('MXN', locale: 'es_MX')
                                    ->placeholder('—'),
                                TextEntry::make('reference')
                                    ->label(__('sales.fields.reference'))
                                    ->visible(fn (?string $state): bool => filled($state)),
                            ]),
                    ]),
                Section::make(__('sales.sections.cancellation'))
                    ->visible(fn (Sale $record): bool => $record->isCancelled())
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cancelled_at')->label(__('sales.fields.cancelled_at'))->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('cancelledBy.name')->label(__('sales.fields.cancelled_by')),
                        TextEntry::make('cancellation_reason')->label(__('sales.fields.cancellation_reason')),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Sale $record): string => "#{$record->folio}")
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('folio')
                    ->label(__('sales.fields.folio'))
                    ->prefix('#')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('sales.fields.date'))
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('user.name')
                    ->label(__('sales.fields.user')),
                TextColumn::make('client.name')
                    ->label(__('sales.fields.client'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('payments.method')
                    ->label(__('sales.fields.payment_method'))
                    ->listWithLineBreaks(),
                TextColumn::make('total')
                    ->label(__('sales.fields.total'))
                    ->money('MXN', locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('sales.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('sales.fields.status'))
                    ->options(SaleStatus::class),
                SelectFilter::make('client_id')
                    ->label(__('sales.fields.client'))
                    ->relationship('client', 'name')
                    ->searchable(),
                SelectFilter::make('payment_method')
                    ->label(__('sales.fields.payment_method'))
                    ->options(PaymentMethod::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $method): Builder => $query->whereHas('payments', fn (Builder $payments) => $payments->where('method', $method)),
                    )),
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label(__('sales.fields.from')),
                        DatePicker::make('until')->label(__('sales.fields.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<', now()->parse($date)->addDay()))),
            ])
            ->recordActions([
                ActionGroup::make([
                    HistoryAction::make(),
                    ViewAction::make(),
                    CancelSaleAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSales::route('/'),
            'view' => ViewSale::route('/{record}'),
            'history' => SaleHistory::route('/{record}/history'),
        ];
    }
}
