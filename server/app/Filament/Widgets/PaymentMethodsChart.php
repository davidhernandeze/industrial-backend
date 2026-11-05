<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Services\Sales;
use App\Support\CurrentBranch;
use Filament\Widgets\ChartWidget;

class PaymentMethodsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return BranchOverview::canView();
    }

    public function getHeading(): string
    {
        return __('dashboard.payment_methods_chart');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $byMethod = app(Sales::class)->overview(app(CurrentBranch::class)->get())['by_method'];

        return [
            'labels' => collect(PaymentMethod::cases())->map->getLabel()->all(),
            'datasets' => [[
                'data' => collect(PaymentMethod::cases())
                    ->map(fn (PaymentMethod $method): float => (float) ($byMethod[$method->value] ?? 0))
                    ->all(),
                'backgroundColor' => ['#f59e0b', '#3b82f6', '#10b981'],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
