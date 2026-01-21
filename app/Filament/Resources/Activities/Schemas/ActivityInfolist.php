<?php

declare(strict_types=1);

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Activity Information'))
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('Date'))
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('description')
                            ->label(__('Action')),

                        TextEntry::make('causer.name')
                            ->label(__('Executed by'))
                            ->placeholder(__('System')),

                        TextEntry::make('subject_type')
                            ->label(__('Subject Type'))
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),

                        TextEntry::make('subject_id')
                            ->label(__('Subject ID'))
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make(__('Details'))
                    ->schema([
                        TextEntry::make('properties')
                            ->label(__('Properties'))
                            ->formatStateUsing(function (mixed $state): string {
                                if (is_string($state)) {
                                    return $state;
                                }

                                if ($state instanceof Collection) {
                                    $state = $state->toArray();
                                }

                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
                            })
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Activity $record): bool => ! empty($record->properties->toArray())),
            ]);
    }
}
