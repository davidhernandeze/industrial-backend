<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Services\Inventory;
use App\Services\Sales;
use App\Support\CurrentBranch;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The state of the current branch at a glance.
 */
class BranchOverview extends StatsOverviewWidget
{
    private const int TREND_DAYS = 7;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->canActAs(Role::BranchManager) === true
            && app(CurrentBranch::class)->get() !== null;
    }

    protected function getHeading(): ?string
    {
        return app(CurrentBranch::class)->get()?->name;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $branch = app(CurrentBranch::class)->get();
        $sales = app(Sales::class);
        $inventory = app(Inventory::class);

        $overview = $sales->overview($branch);
        $days = $sales->dailyTotals($branch, self::TREND_DAYS);
        $yesterday = $days->slice(-2, 1)->first();
        $totals = $days->pluck('total')->map(fn (string $total): float => (float) $total)->all();
        $negative = $inventory->negativeStockCount($branch);

        return [
            $this->salesToday($overview['total'], $yesterday['total'])->chart($totals),
            Stat::make(__('dashboard.tickets'), (string) $overview['count'])
                ->description(__('dashboard.today'))
                ->chart($days->pluck('count')->all())
                ->color('primary'),
            Stat::make(__('dashboard.average_ticket'), $this->money($overview['average']))
                ->description(__('dashboard.today')),
            Stat::make(__('dashboard.month_sales'), $this->money($overview['month_total']))
                ->description(__('dashboard.this_month'))
                ->descriptionIcon(Heroicon::OutlinedCalendarDays),
            Stat::make(__('dashboard.inventory_value'), $this->money($inventory->valueAtSalePrice($branch)))
                ->description(__('dashboard.at_sale_price'))
                ->descriptionIcon(Heroicon::OutlinedArchiveBox),
            Stat::make(__('dashboard.negative_stock'), (string) $negative)
                ->description(__('dashboard.products'))
                ->descriptionIcon($negative > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($negative > 0 ? 'danger' : 'success'),
        ];
    }

    private function salesToday(string $today, string $yesterday): Stat
    {
        $stat = Stat::make(__('dashboard.sales_today'), $this->money($today));

        if (bccomp($yesterday, '0', 2) === 0) {
            return $stat->description(__('dashboard.no_sales_yesterday'))->color('gray');
        }

        $change = round(((float) $today - (float) $yesterday) / (float) $yesterday * 100);
        $up = $change >= 0;

        return $stat
            ->description(__('dashboard.versus_yesterday', ['change' => ($up ? '+' : '').$change]))
            ->descriptionIcon($up ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
            ->color($up ? 'success' : 'danger');
    }

    private function money(string $amount): string
    {
        return Number::currency((float) $amount, 'MXN', 'es_MX');
    }
}
