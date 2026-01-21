<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes;

use App\Filament\Resources\OnesiBoxes\Pages\CreateOnesiBox;
use App\Filament\Resources\OnesiBoxes\Pages\EditOnesiBox;
use App\Filament\Resources\OnesiBoxes\Pages\ListOnesiBoxes;
use App\Filament\Resources\OnesiBoxes\Schemas\OnesiBoxForm;
use App\Filament\Resources\OnesiBoxes\Tables\OnesiBoxesTable;
use App\Models\OnesiBox;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class OnesiBoxResource extends Resource
{
    protected static ?string $model = OnesiBox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTv;

    protected static UnitEnum|string|null $navigationGroup = 'Appliances';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('OnesiBox');
    }

    public static function getPluralModelLabel(): string
    {
        return __('OnesiBox');
    }

    public static function form(Schema $schema): Schema
    {
        return OnesiBoxForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnesiBoxesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnesiBoxes::route('/'),
            'create' => CreateOnesiBox::route('/create'),
            'edit' => EditOnesiBox::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<OnesiBox>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
