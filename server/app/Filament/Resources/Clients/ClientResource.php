<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\Clients\Pages\ClientHistory;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\RoleGuardedResource;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends RoleGuardedResource
{
    protected static ?string $model = Client::class;

    protected static string|array $routeMiddleware = 'role:branch_manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('clients.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('clients.plural_model_label');
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'email', 'tax_id'];
    }

    /**
     * All the till asks for, so adding a client doesn't hold up the line.
     *
     * @return list<Field>
     */
    public static function essentialFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('clients.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('phone')
                ->label(__('clients.fields.phone'))
                ->tel()
                ->maxLength(30),
        ];
    }

    /**
     * @return list<Field>
     */
    private static function otherFields(): array
    {
        return [
            TextInput::make('email')
                ->label(__('clients.fields.email'))
                ->email()
                ->maxLength(255),
            TextInput::make('tax_id')
                ->label(__('clients.fields.tax_id'))
                ->maxLength(13),
            Textarea::make('address')
                ->label(__('clients.fields.address'))
                ->rows(2)
                ->maxLength(65535),
            Textarea::make('notes')
                ->label(__('clients.fields.notes'))
                ->rows(2)
                ->maxLength(65535),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([...self::essentialFields(), ...self::otherFields()])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('clients.fields.name')),
                TextEntry::make('phone')
                    ->label(__('clients.fields.phone'))
                    ->placeholder('—'),
                TextEntry::make('email')
                    ->label(__('clients.fields.email'))
                    ->placeholder('—'),
                TextEntry::make('tax_id')
                    ->label(__('clients.fields.tax_id'))
                    ->placeholder('—'),
                TextEntry::make('address')
                    ->label(__('clients.fields.address'))
                    ->placeholder('—'),
                TextEntry::make('notes')
                    ->label(__('clients.fields.notes'))
                    ->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('clients.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('clients.fields.phone'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label(__('clients.fields.email'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('tax_id')
                    ->label(__('clients.fields.tax_id'))
                    ->searchable()
                    ->placeholder('—'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    HistoryAction::make(),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
            'view' => ViewClient::route('/{record}'),
            'history' => ClientHistory::route('/{record}/history'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
