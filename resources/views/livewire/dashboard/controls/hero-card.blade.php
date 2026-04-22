<div data-hero-state="{{ $state }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4" aria-live="polite">
    @if($state === 'idle')
        <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Online · In attesa</flux:text>
        </div>
        <flux:text class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
            Ultimo contatto: {{ $onesiBox->last_seen_at?->diffForHumans() ?? '—' }}
        </flux:text>
    @else
        {{-- other variants follow in later tasks --}}
    @endif
</div>
