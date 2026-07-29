<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes;

use App\Filament\Resources\OnesiBoxes\Pages\CreateOnesiBox;
use App\Filament\Resources\OnesiBoxes\Pages\EditOnesiBox;
use App\Filament\Resources\OnesiBoxes\Pages\ListOnesiBoxes;
use App\Filament\Resources\OnesiBoxes\Pages\ManageScreenshots;
use App\Filament\Resources\OnesiBoxes\Pages\OnesiBoxDiagnostics;
use App\Filament\Resources\OnesiBoxes\RelationManagers\CaregiversRelationManager;
use App\Filament\Resources\OnesiBoxes\RelationManagers\CustomCommandsRelationManager;
use App\Filament\Resources\OnesiBoxes\RelationManagers\TokensRelationManager;
use App\Filament\Resources\OnesiBoxes\Schemas\OnesiBoxForm;
use App\Filament\Resources\OnesiBoxes\Tables\OnesiBoxesTable;
use App\Models\OnesiBox;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
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

    protected static UnitEnum|string|null $navigationGroup = 'Dispositivi';

    protected static ?int $navigationSort = 1;

    /**
     * Record pages are presented as tabs above the content rather than as a
     * side rail, so the diagnostics view keeps the full content width.
     */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

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
            CaregiversRelationManager::class,
            CustomCommandsRelationManager::class,
            TokensRelationManager::class,
        ];
    }

    /**
     * Tabs shown on every single-record page of the resource.
     *
     * @return array<int, \Filament\Navigation\NavigationItem>
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditOnesiBox::class,
            OnesiBoxDiagnostics::class,
            ManageScreenshots::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnesiBoxes::route('/'),
            'create' => CreateOnesiBox::route('/create'),
            'edit' => EditOnesiBox::route('/{record}/edit'),
            'diagnostics' => OnesiBoxDiagnostics::route('/{record}/diagnostics'),
            'screenshots' => ManageScreenshots::route('/{record}/screenshots'),
        ];
    }

    /**
     * @return Builder<OnesiBox>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<OnesiBox> */
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
