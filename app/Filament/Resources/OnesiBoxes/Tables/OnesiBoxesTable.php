<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Tables;

use App\Enums\MeetingJoinMode;
use App\Models\OnesiBox;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OnesiBoxesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('recipient'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serial_number')
                    ->label(__('Numero Seriale'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recipient.full_name')
                    ->label(__('Beneficiario'))
                    ->state(fn (OnesiBox $record): ?string => $record->recipient?->full_name)
                    ->searchable(['recipient.first_name', 'recipient.last_name'])
                    ->placeholder(__('Non assegnato')),

                IconColumn::make('is_online')
                    ->label(__('Stato'))
                    ->state(fn (OnesiBox $record): string => self::getOnlineStatus($record))
                    ->icon(fn (string $state): string => match ($state) {
                        'online' => 'heroicon-o-signal',
                        'offline' => 'heroicon-o-signal-slash',
                        'never' => 'heroicon-o-question-mark-circle',
                        'disabled' => 'heroicon-o-no-symbol',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        'never' => 'warning',
                        'disabled' => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'online' => __('Online'),
                        'offline' => __('Offline'),
                        'never' => __('Mai connesso'),
                        'disabled' => __('Disabilitato'),
                        default => __('Sconosciuto'),
                    }),

                TextColumn::make('last_seen_at')
                    ->label(__('Ultimo Contatto'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder(__('Mai'))
                    ->sortable(),

                TextColumn::make('firmware_version')
                    ->label(__('Firmware'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('app_version')
                    ->label(__('Versione SW'))
                    ->badge()
                    ->color(fn (?string $state): string => $state && version_compare($state, config('onesiforo.onesibox_min_version'), '>=') ? 'success' : 'danger')
                    ->placeholder('N/D')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('meeting_join_mode')
                    ->label(__('Modalità Join'))
                    ->badge()
                    ->formatStateUsing(fn (MeetingJoinMode|string|null $state): string => match (true) {
                        $state instanceof MeetingJoinMode => $state->getLabel(),
                        is_string($state) && $state !== '' => MeetingJoinMode::tryFrom($state)?->getLabel() ?? 'N/D',
                        default => 'N/D',
                    })
                    ->color(fn (MeetingJoinMode|string|null $state): string => match (true) {
                        $state instanceof MeetingJoinMode => $state === MeetingJoinMode::Auto ? 'success' : 'gray',
                        is_string($state) && MeetingJoinMode::tryFrom($state) === MeetingJoinMode::Auto => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('next_meeting')
                    ->label(__('Prossima adunanza'))
                    ->state(function (OnesiBox $record): string {
                        $congregation = $record->recipient?->congregation;
                        if (! $congregation) {
                            return 'N/D';
                        }
                        $next = $congregation->nextMeeting();

                        return $next['type']->getLabel().' — '.$next['scheduled_at']->format('D d/m H:i');
                    })
                    ->toggleable(),

                TextColumn::make('caregivers_count')
                    ->label(__('Caregiver'))
                    ->counts('caregivers')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                IconColumn::make('is_active')
                    ->label(__('Attivo'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('Stato Attivazione')),

                SelectFilter::make('online_status')
                    ->label(__('Stato Connessione'))
                    ->options([
                        'online' => __('Online'),
                        'offline' => __('Offline'),
                        'never' => __('Mai connesso'),
                    ])
                    ->query(fn ($query, array $data) => match ($data['value']) {
                        'online' => $query->where('last_seen_at', '>=', now()->subMinutes(5)),
                        'offline' => $query->where('last_seen_at', '<', now()->subMinutes(5)),
                        'never' => $query->whereNull('last_seen_at'),
                        default => $query,
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    private static function getOnlineStatus(OnesiBox $record): string
    {
        if (! $record->is_active) {
            return 'disabled';
        }

        if ($record->last_seen_at === null) {
            return 'never';
        }

        return $record->isOnline() ? 'online' : 'offline';
    }
}
