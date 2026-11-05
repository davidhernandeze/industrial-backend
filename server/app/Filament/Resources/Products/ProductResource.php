<?php

namespace App\Filament\Resources\Products;

use App\Filament\Actions\HistoryAction;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\Products\Pages\ProductHistory;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\RoleGuardedResource;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductResource extends RoleGuardedResource
{
    protected static ?string $model = Product::class;

    protected static string|array $routeMiddleware = 'role:admin';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('products.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('products.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('products.navigation_label');
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('products.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('barcode')
                    ->label(__('products.fields.barcode'))
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->autocomplete(false)
                    // Scanners finish with Enter, which would otherwise submit the form.
                    ->extraInputAttributes(['x-on:keydown.enter.prevent' => '']),
                TextInput::make('sku')
                    ->label(__('products.fields.sku'))
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('brand')
                    ->label(__('products.fields.brand'))
                    ->maxLength(255),
                Textarea::make('description')
                    ->label(__('products.fields.description'))
                    ->rows(3)
                    ->maxLength(65535),
                FileUpload::make('picture')
                    ->label(__('products.fields.picture'))
                    ->image()
                    ->disk('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    // The file is named after the id, which only exists once the product is saved.
                    ->storeFiles(false)
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (FileUpload $component, Product $record): void {
                        $file = collect($component->getRawState())->first();

                        if ($file instanceof TemporaryUploadedFile) {
                            $record->replacePicture($file);
                        } elseif ($file === null && $record->picture !== null) {
                            $record->removePicture();
                        }
                    }),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('picture_url')
                    ->label(__('products.fields.picture'))
                    ->placeholder('—'),
                TextEntry::make('name')
                    ->label(__('products.fields.name')),
                TextEntry::make('barcode')
                    ->label(__('products.fields.barcode'))
                    ->placeholder('—'),
                TextEntry::make('sku')
                    ->label(__('products.fields.sku'))
                    ->placeholder('—'),
                TextEntry::make('brand')
                    ->label(__('products.fields.brand'))
                    ->placeholder('—'),
                TextEntry::make('description')
                    ->label(__('products.fields.description'))
                    ->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('picture_url')
                    ->label(__('products.fields.picture')),
                TextColumn::make('name')
                    ->label(__('products.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label(__('products.fields.barcode'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('sku')
                    ->label(__('products.fields.sku'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('brand')
                    ->label(__('products.fields.brand'))
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
            'index' => ManageProducts::route('/'),
            'view' => ViewProduct::route('/{record}'),
            'history' => ProductHistory::route('/{record}/history'),
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
