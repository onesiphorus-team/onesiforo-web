<div wire:poll.10s>
    @if($this->hasActiveSession)
        @php $session = $this->activeSession; @endphp
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="sm">
                    <flux:icon name="play-circle" class="w-5 h-5 inline-block mr-2 text-green-500" />
                    Sessione in corso
                </flux:heading>
                <flux:badge color="success" size="sm">
                    {{ $session->status->getLabel() }}
                </flux:badge>
            </div>

            <div class="space-y-3">
                {{-- Current video --}}
                @if($this->currentVideo)
                    <div>
                        <flux:text class="text-xs text-zinc-500">Video attuale</flux:text>
                        <flux:text class="font-medium truncate">
                            {{ $this->currentVideo->title ?? $this->currentVideo->media_url }}
                        </flux:text>
                    </div>
                @endif

                {{-- Progress --}}
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <flux:text class="text-zinc-500">
                            Video {{ $session->current_position + 1 }} di {{ $this->totalItems }}
                        </flux:text>
                        <flux:text class="text-zinc-500">
                            {{ $this->progressPercent }}%
                        </flux:text>
                    </div>
                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                        <div
                            class="bg-green-500 h-2 rounded-full transition-all duration-500"
                            style="width: {{ $this->progressPercent }}%"
                        ></div>
                    </div>
                </div>

                {{-- Stats grid --}}
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="p-2 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg">
                        <flux:text class="text-xs text-zinc-500">Tempo rimasto</flux:text>
                        <flux:text class="font-medium text-sm">{{ $this->formatTimeRemaining($this->timeRemainingSeconds) }}</flux:text>
                    </div>
                    <div class="p-2 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg">
                        <flux:text class="text-xs text-zinc-500">Riprodotti</flux:text>
                        <flux:text class="font-medium text-sm">{{ $session->items_played }}</flux:text>
                    </div>
                    <div class="p-2 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg">
                        <flux:text class="text-xs text-zinc-500">Saltati</flux:text>
                        <flux:text class="font-medium text-sm">{{ $session->items_skipped }}</flux:text>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
