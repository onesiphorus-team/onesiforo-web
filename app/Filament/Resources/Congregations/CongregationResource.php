<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations;

use App\Filament\Resources\Congregations\Pages\CreateCongregation;
use App\Filament\Resources\Congregations\Pages\EditCongregation;
use App\Filament\Resources\Congregations\Pages\ListCongregations;
use App\Filament\Resources\Congregations\RelationManagers\RecipientsRelationManager;
use App\Filament\Resources\Congregations\Schemas\CongregationForm;
use App\Filament\Resources\Congregations\Tables\CongregationsTable;
use App\Models\Congregation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CongregationResource extends Resource
{
    protected static ?string $model = Congregation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Congregazione');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Congregazioni');
    }

    public static function form(Schema $schema): Schema
    {
        return CongregationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CongregationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCongregations::route('/'),
            'create' => CreateCongregation::route('/create'),
            'edit' => EditCongregation::route('/{record}/edit'),
        ];
    }
}
