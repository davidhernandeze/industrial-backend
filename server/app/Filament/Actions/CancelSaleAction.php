<?php

namespace App\Filament\Actions;

use App\Models\Sale;
use App\Services\SaleRejected;
use App\Services\Sales;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class CancelSaleAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelSale';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('sales.cancel.label'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Sale $record): bool => ! $record->isCancelled())
            ->requiresConfirmation()
            ->modalHeading(__('sales.cancel.heading'))
            ->modalDescription(__('sales.cancel.description'))
            ->modalSubmitActionLabel(__('sales.cancel.confirm'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('sales.fields.cancellation_reason'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Sale $record, array $data): void {
                try {
                    app(Sales::class)->cancel($record, $data['reason']);
                } catch (SaleRejected $rejected) {
                    Notification::make()->title($rejected->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('sales.cancel.done'))->success()->send();
            });
    }
}
