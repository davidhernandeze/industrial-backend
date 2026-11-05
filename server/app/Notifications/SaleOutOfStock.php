<?php

namespace App\Notifications;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A sale took one or more products below zero.
 */
class SaleOutOfStock extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $lineIds  The movements that went negative
     */
    public function __construct(public Sale $sale, public array $lineIds) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('notifications.sale_out_of_stock.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.sale_out_of_stock.intro', ['folio' => $this->sale->folio]));

        foreach ($this->productLines() as $line) {
            $mail->line($line);
        }

        return $mail
            ->line(__('notifications.sale_out_of_stock.flagged'))
            ->action(__('notifications.sale_out_of_stock.action'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return PanelNotification::make()
            ->title($this->title())
            ->body(implode(' ', $this->productLines()))
            ->danger()
            ->actions([
                Action::make('view')
                    ->label(__('notifications.sale_out_of_stock.action'))
                    ->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    private function title(): string
    {
        return __('notifications.sale_out_of_stock.title', ['branch' => $this->sale->branch->name]);
    }

    /**
     * @return list<string>
     */
    private function productLines(): array
    {
        return InventoryMovement::query()
            ->whereKey($this->lineIds)
            ->with('branchProduct.product')
            ->get()
            ->map(fn (InventoryMovement $line): string => __('notifications.sale_out_of_stock.line', [
                'product' => $line->branchProduct->product->name,
                'quantity' => $line->branchProduct->unit->format(ltrim($line->quantity, '-')),
                'stock' => $line->branchProduct->unit->format($line->balance_after),
            ]))
            ->all();
    }

    private function url(): string
    {
        return SaleResource::getUrl('view', ['record' => $this->sale]);
    }
}
