<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The signed-in user's notifications.
 */
class NotificationsTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.notifications'))
            ->query(fn (): Builder => auth()->user()->notifications()->getQuery())
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('dashboard.no_notifications'))
            ->emptyStateIcon(Heroicon::OutlinedBell)
            ->columns([
                IconColumn::make('unread')
                    ->label('')
                    ->state(fn (DatabaseNotification $record): bool => $record->read_at === null)
                    ->icon(fn (bool $state): Heroicon => $state ? Heroicon::OutlinedBell : Heroicon::OutlinedCheck)
                    ->color(fn (bool $state): string => $state ? 'primary' : 'gray'),
                TextColumn::make('data.title')
                    ->label(__('dashboard.notification'))
                    ->weight('medium')
                    ->description(fn (DatabaseNotification $record): ?string => $record->data['body'] ?? null)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('dashboard.date'))
                    ->since(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('open')
                        ->label(__('dashboard.open'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (DatabaseNotification $record): ?string => $record->data['actions'][0]['url'] ?? null)
                        ->visible(fn (DatabaseNotification $record): bool => filled($record->data['actions'][0]['url'] ?? null)),
                    Action::make('markAsRead')
                        ->label(__('dashboard.mark_as_read'))
                        ->icon(Heroicon::OutlinedCheck)
                        ->visible(fn (DatabaseNotification $record): bool => $record->read_at === null)
                        ->action(fn (DatabaseNotification $record) => $record->markAsRead()),
                ]),
            ])
            ->headerActions([
                Action::make('markAllAsRead')
                    ->label(__('dashboard.mark_all_as_read'))
                    ->color('gray')
                    ->action(fn () => auth()->user()->unreadNotifications->markAsRead()),
            ]);
    }
}
