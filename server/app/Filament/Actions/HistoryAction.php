<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

class HistoryAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'history';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('history.label'))
            ->icon('app-history')
            ->color('gray')
            ->url(fn (Model $record, Page $livewire): string => $livewire::getResource()::getUrl('history', ['record' => $record]));
    }
}
