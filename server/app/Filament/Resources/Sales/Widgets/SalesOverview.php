<?php

namespace App\Filament\Resources\Sales\Widgets;

use App\Enums\PaymentMethod;
use App\Services\Sales;
use App\Support\CurrentBranch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Today's figures for the current branch. Cancelled sales do not count as sold.
 */
class SalesOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $overview = app(Sales::class)->overview(app(CurrentBranch::class)->get());
        $today = __('sales.overview.today');

        $methods = collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): Stat => Stat::make(
            $method->getLabel(),
            $this->money($overview['by_method'][$method->value] ?? '0'),
        )->description($today));

        return [
            Stat::make(__('sales.overview.total'), $this->money($overview['total']))->description($today),
            Stat::make(__('sales.overview.count'), (string) $overview['count'])->description($today),
            Stat::make(__('sales.overview.average'), $this->money($overview['average']))->description($today),
            Stat::make(__('sales.overview.cancelled'), (string) $overview['cancelled'])
                ->description($today)
                ->color($overview['cancelled'] > 0 ? 'danger' : 'gray'),
            ...$methods,
            Stat::make(__('sales.overview.month_total'), $this->money($overview['month_total']))
                ->description(__('sales.overview.this_month')),
        ];
    }

    private function money(string $amount): string
    {
        return Number::currency((float) $amount, 'MXN', 'es_MX');
    }
}
