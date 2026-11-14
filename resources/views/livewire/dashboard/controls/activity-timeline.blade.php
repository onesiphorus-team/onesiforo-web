<div>
    <ol class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
        @forelse ($this->entries as $entry)
            @php
                $startedAt = $entry->startedAt->copy()->setTimezone($displayTimezone);
                $endedAt = $entry->endedAt?->copy()->setTimezone($displayTimezone);
            @endphp
            <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0" wire:key="activity-{{ $entry->kind->value }}-{{ $entry->startedAt->getTimestamp() }}">
                <span class="w-12 shrink-0 text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $startedAt->format('H:i') }}</span>
                <span class="relative flex aspect-video w-14 shrink-0 items-center justify-center overflow-hidden rounded-md bg-zinc-100 dark:bg-zinc-700">
                    @if ($entry->thumbnailUrl)
                        <img src="{{ $entry->thumbnailUrl }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="size-full object-cover">
                    @else
                        <flux:icon name="{{ $entry->iconName }}" variant="mini" class="size-4 text-zinc-400" />
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm text-zinc-800 dark:text-zinc-100">{{ $entry->label }}</span>
                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                        @if ($entry->isInProgress())
                            <span class="font-medium text-green-700 dark:text-green-400">in corso</span>
                        @else
                            fino alle {{ $endedAt?->format('H:i') }}
                        @endif
                        @if ($entry->metadata)
                            · {{ $entry->metadata }}
                        @endif
                    </span>
                </span>
            </li>
        @empty
            <li class="py-2 text-sm text-zinc-500 dark:text-zinc-400">Oggi il box non ha ancora mostrato nulla.</li>
        @endforelse
    </ol>
</div>
