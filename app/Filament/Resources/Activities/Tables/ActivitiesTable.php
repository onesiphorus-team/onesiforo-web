<?php

declare(strict_types=1);

namespace App\Filament\Resources\Activities\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Data'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label(__('Utente'))
                    ->placeholder(__('Sistema'))
                    ->searchable(),

                TextColumn::make('description')
                    ->label(__('Azione'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('subject_type')
                    ->label(__('Tipo Oggetto'))
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-')
                    ->sortable(),

                TextColumn::make('subject_id')
                    ->label(__('ID Oggetto'))
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('causer')
                    ->label(__('Utente'))
                    ->options(fn (): array => User::query()->pluck('name', 'id')->toArray())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $query, $value): Builder => $query->where('causer_id', $value)
                    )),

                SelectFilter::make('description')
                    ->label(__('Tipo Azione'))
                    ->options(fn (): array => self::descriptionOptions()),

                SelectFilter::make('subject_type')
                    ->label(__('Tipo Oggetto'))
                    ->options(fn (): array => Activity::query()
                        ->select('subject_type')
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->get()
                        ->mapWithKeys(fn (Activity $activity): array => [
                            $activity->subject_type => class_basename($activity->subject_type),
                        ])
                        ->all()
                    ),

                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('Da')),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('A')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * The distinct actions recorded in the log, for the action filter.
     *
     * `description` is a TEXT column, so listing its distinct values reads
     * the whole table. Filament asks for these options on every render of the
     * page, hence the cache. After ten minutes the list is refreshed in the
     * background once the response has been sent, so nobody waits for the
     * scan; a brand new kind of action shows up on the page view after that.
     *
     * @return array<string, string>
     */
    public static function descriptionOptions(): array
    {
        return Cache::flexible(
            'activity-log:description-options',
            [600, 86400],
            fn (): array => Activity::query()
                ->select('description')
                ->distinct()
                ->pluck('description', 'description')
                ->toArray(),
        );
    }
}
