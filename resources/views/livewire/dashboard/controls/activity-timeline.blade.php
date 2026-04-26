<div class="space-y-2">
    @forelse ($this->entries as $entry)
        <div class="flex items-center gap-3 text-sm" wire:key="activity-{{ $entry->kind->value }}-{{ $entry->startedAt->getTimestamp() }}">
            <flux:icon name="{{ $entry->iconName }}" class="h-4 w-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
            <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-300">
                {{ $entry->startedAt->copy()->setTimezone($displayTimezone)->format('H:i') }}–{{ $entry->endedAt?->copy()->setTimezone($displayTimezone)->format('H:i') ?? 'in corso' }}
            </span>
            <span class="text-zinc-700 dark:text-zinc-200">{{ $entry->label }}</span>
            @if ($entry->metadata)
                <span class="text-xs text-zinc-500 dark:text-zinc-400">· {{ $entry->metadata }}</span>
            @endif
        </div>
    @empty
        <p class="text-sm italic text-zinc-500 dark:text-zinc-400">Nessuna attività registrata oggi.</p>
    @endforelse
</div>
