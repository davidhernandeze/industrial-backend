<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Filament\Concerns\ShownInBranchSection;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\BranchProduct;
use App\Models\Client;
use App\Services\SaleRejected;
use App\Services\Sales;
use App\Support\CurrentBranch;
use App\Support\PosCart;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * The till, and a cashier's whole layout. A custom Livewire page: prices and totals stay on the server, the cart lives in
 * PosCart, and the view adds only a little Alpine to keep the scanner input responsive.
 */
class PointOfSale extends Page
{
    use ShownInBranchSection;

    private const int SEARCH_LIMIT = 8;

    protected string $view = 'filament.pages.point-of-sale';

    protected static string|array $routeMiddleware = 'role:exclusive,cashier';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $slug = 'point-of-sale';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @var list<array{id: int, name: string, price: string, picture: ?string}>
     */
    public array $results = [];

    /**
     * Whether a search is active. Without one the panel shows the best sellers.
     */
    public bool $searching = false;

    public static function getNavigationLabel(): string
    {
        return __('point_of_sale.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('point_of_sale.title');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Cashier) === true
            && app(CurrentBranch::class)->get() !== null;
    }

    /**
     * An exact barcode or SKU, from a scanner or typed and confirmed with Enter.
     */
    public function scan(string $code): void
    {
        $this->clearSearch();
        $code = trim($code);

        $row = $this->rows()
            ->whereHas('product', fn (Builder $products) => $products
                ->where('barcode', $code)
                ->orWhere('sku', $code))
            ->first();

        if ($row === null) {
            $this->search($code);

            if ($this->results === []) {
                Notification::make()->title(__('point_of_sale.not_found', ['code' => $code]))->danger()->send();
            }

            return;
        }

        $this->addRow($row);
    }

    public function search(string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            $this->clearSearch();

            return;
        }

        $this->searching = true;
        $this->results = $this->rows()
            ->whereHas('product', fn (Builder $products) => $products->where(fn (Builder $matches) => $matches
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")))
            ->with('product')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->map(fn (BranchProduct $row): array => $this->productForList($row))
            ->all();
    }

    public function addProduct(int $branchProductId): void
    {
        $this->clearSearch();

        $row = $this->rows()->find($branchProductId);

        if ($row !== null) {
            $this->addRow($row);
        }
    }

    public function updateQuantity(int $branchProductId, string $quantity): void
    {
        $row = $this->rows()->find($branchProductId);

        if ($row === null) {
            return;
        }

        if (! is_numeric($quantity) || bccomp($quantity, '0', 3) < 0) {
            Notification::make()->title(__('point_of_sale.invalid_quantity'))->danger()->send();

            return;
        }

        if (bccomp($quantity, '0', 3) === 0) {
            $this->removeLine($branchProductId);

            return;
        }

        if (! $row->unit->allowsFractions() && bccomp($quantity, bcadd($quantity, '0', 0), 3) !== 0) {
            Notification::make()->title(__('inventory_movements.errors.whole'))->danger()->send();

            return;
        }

        PosCart::current()->set($branchProductId, $quantity);
    }

    /**
     * The plus and minus buttons. Going to zero removes the line.
     */
    public function changeQuantity(int $branchProductId, int $direction): void
    {
        $row = $this->rows()->find($branchProductId);
        $current = PosCart::current()->lines()[$branchProductId] ?? null;

        if ($row === null || $current === null) {
            return;
        }

        $this->updateQuantity(
            $branchProductId,
            bcadd($current, $direction > 0 ? $row->unit->step() : '-'.$row->unit->step(), 3),
        );
    }

    public function removeLine(int $branchProductId): void
    {
        PosCart::current()->remove($branchProductId);
    }

    public function checkoutAction(): Action
    {
        return Action::make('checkout')
            ->label(__('point_of_sale.checkout'))
            ->keyBindings(['f2'])
            ->size('xl')
            ->extraAttributes(['class' => 'w-full'])
            ->disabled(fn (): bool => PosCart::current()->items()->isEmpty())
            ->modalHeading(__('point_of_sale.checkout'))
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('point_of_sale.confirm'))
            ->fillForm(fn (): array => [
                'expected_total' => PosCart::current()->total(),
                'amount_received' => PosCart::current()->total(),
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->schema([
                Hidden::make('expected_total'),
                TextEntry::make('due')
                    ->label(__('sales.fields.total'))
                    ->state(fn (Get $get): string => $this->money((string) $get('expected_total'))),
                ToggleButtons::make('payment_method')
                    ->label(__('sales.fields.payment_method'))
                    ->options(PaymentMethod::class)
                    ->inline()
                    ->required()
                    ->live(),
                TextInput::make('amount_received')
                    ->label(__('sales.fields.amount_received'))
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->visible(fn (Get $get): bool => $this->selectedMethod($get) === PaymentMethod::Cash),
                TextInput::make('reference')
                    ->label(__('sales.fields.reference'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => ! in_array($this->selectedMethod($get), [null, PaymentMethod::Cash], true)),
                Select::make('client_id')
                    ->label(__('sales.fields.client'))
                    ->placeholder(__('sales.no_client'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Client::query()
                        ->matching($search)
                        ->orderBy('name')
                        ->limit(self::SEARCH_LIMIT)
                        ->get()
                        ->mapWithKeys(fn (Client $client): array => [$client->id => $client->select_label])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Client::query()->find($value)?->select_label)
                    ->createOptionForm(ClientResource::essentialFields())
                    ->createOptionUsing(fn (array $data): int => Client::query()->create($data)->getKey())
                    ->createOptionAction(fn (Action $action): Action => $action
                        ->modalHeading(__('clients.new'))
                        ->modalWidth(Width::Medium)),
            ])
            ->action(fn (array $data, Action $action) => $this->completeSale($data, $action));
    }

    public function clearAction(): Action
    {
        return Action::make('clear')
            ->label(__('point_of_sale.clear'))
            ->color('gray')
            ->disabled(fn (): bool => PosCart::current()->isEmpty())
            ->requiresConfirmation()
            ->modalHeading(__('point_of_sale.clear_confirmation'))
            ->action(function (): void {
                PosCart::current()->clear();
                $this->dispatch('pos-focus-scan');
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $cart = PosCart::current();

        return [
            'items' => $cart->items(),
            'total' => $this->money($cart->total()),
            'currentBranch' => app(CurrentBranch::class),
            'bestSellers' => $this->searching ? [] : $this->bestSellers(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function completeSale(array $data, Action $action): void
    {
        $cart = PosCart::current();

        if (bccomp((string) $data['expected_total'], $cart->total(), 2) !== 0) {
            Notification::make()->title(__('point_of_sale.total_changed'))->warning()->send();
            $action->halt();

            return;
        }

        $method = $data['payment_method'] instanceof PaymentMethod
            ? $data['payment_method']
            : PaymentMethod::from($data['payment_method']);

        try {
            $sale = app(Sales::class)->create(
                $cart->uuid(),
                $cart->items()->mapWithKeys(fn (BranchProduct $row): array => [$row->id => $row->cart_quantity])->all(),
                $method,
                $method === PaymentMethod::Cash ? $cart->total() : null,
                $data['reference'] ?? null,
                isset($data['client_id']) ? (int) $data['client_id'] : null,
            );
        } catch (SaleRejected $rejected) {
            Notification::make()->title($rejected->getMessage())->danger()->send();
            $action->halt();

            return;
        }

        $cart->clear();

        Notification::make()
            ->title(__('point_of_sale.completed', ['folio' => $sale->folio]))
            ->success()
            ->send();

        $this->dispatch('pos-focus-scan');
    }

    /**
     * @return list<array{id: int, name: string, price: string, picture: ?string}>
     */
    private function bestSellers(): array
    {
        return app(Sales::class)
            ->bestSellers(app(CurrentBranch::class)->get())
            ->map(fn (BranchProduct $row): array => $this->productForList($row))
            ->all();
    }

    /**
     * @return array{id: int, name: string, price: string, picture: ?string}
     */
    private function productForList(BranchProduct $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->product->name,
            'price' => $this->money($row->price),
            'picture' => $row->product->picture_url,
        ];
    }

    private function clearSearch(): void
    {
        $this->searching = false;
        $this->results = [];
    }

    private function addRow(BranchProduct $row): void
    {
        PosCart::current()->add($row);

        if ($row->unit === ProductUnit::Kilogram) {
            $this->dispatch('pos-focus-quantity', id: $row->id);

            return;
        }

        // Adding reorders the lines, which can drop focus and lose the next scan.
        $this->dispatch('pos-focus-scan');
    }

    /**
     * @return Builder<BranchProduct>
     */
    private function rows(): Builder
    {
        return BranchProduct::query()
            ->forBranch(app(CurrentBranch::class)->get())
            ->whereHas('product', fn (Builder $products) => $products->withoutTrashed());
    }

    private function selectedMethod(Get $get): ?PaymentMethod
    {
        $method = $get('payment_method');

        return $method instanceof PaymentMethod ? $method : PaymentMethod::tryFrom((string) $method);
    }

    private function money(string $amount): string
    {
        return Number::currency((float) $amount, 'MXN', 'es_MX');
    }
}
