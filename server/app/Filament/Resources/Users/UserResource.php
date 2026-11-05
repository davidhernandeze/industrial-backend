<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Actions\HistoryAction;
use App\Filament\Forms\Components\BranchAssignment;
use App\Filament\Resources\RoleGuardedResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\Pages\UserHistory;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Password;

class UserResource extends RoleGuardedResource
{
    protected static ?string $model = User::class;

    protected static string|array $routeMiddleware = 'role:admin';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('users.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('users.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('users.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(__('users.fields.password'))
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Select::make('role')
                    ->label(__('users.fields.role'))
                    ->options(Role::class)
                    ->required()
                    ->disabledOn('edit')
                    ->live(),
                BranchAssignment::make('branches')
                    ->label(__('users.fields.branches'))
                    ->required()
                    ->visible(fn (Get $get): bool => ! in_array(self::selectedRole($get), [null, Role::Admin], true)),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('users.fields.name')),
                TextEntry::make('email')
                    ->label(__('users.fields.email')),
                TextEntry::make('role')
                    ->label(__('users.fields.role'))
                    ->badge(),
                TextEntry::make('branches.name')
                    ->label(__('users.fields.branches'))
                    ->badge()
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
                    ->label(__('users.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('users.fields.email'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('users.fields.role'))
                    ->badge(),
                TextColumn::make('branches.name')
                    ->label(__('users.fields.branches'))
                    ->badge()
                    ->limitList(3)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('users.fields.role'))
                    ->options(Role::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    HistoryAction::make(),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->hidden(fn (User $record): bool => $record->is(auth()->user())),
                    RestoreAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
            'history' => UserHistory::route('/{record}/history'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    private static function selectedRole(Get $get): ?Role
    {
        $role = $get('role');

        return $role instanceof Role ? $role : Role::tryFrom((string) $role);
    }
}
