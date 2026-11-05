<?php

namespace App\Filament\Widgets;

use App\Services\Sales;
use App\Support\CurrentBranch;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    private const int DAYS = 14;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return BranchOverview::canView();
    }

    public function getHeading(): string
    {
        return __('dashboard.sales_chart', ['days' => self::DAYS]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'suggestedMax' => 100]]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $days = app(Sales::class)->dailyTotals(app(CurrentBranch::class)->get(), self::DAYS);

        return [
            'labels' => $days->keys()->map(fn (string $date): string => CarbonImmutable::parse($date)->format('d/m'))->all(),
            'datasets' => [[
                'label' => __('dashboard.sales'),
                'data' => $days->pluck('total')->map(fn (string $total): float => (float) $total)->all(),
                'fill' => true,
                'tension' => 0.35,
            ]],
        ];
    }
}
