<div>
    @if($this->canControl)
        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 pb-[env(safe-area-inset-bottom)]"
             aria-label="Azioni rapide">
            <div class="mx-auto flex max-w-4xl items-stretch justify-around px-2 py-2 {{ $this->isOnline ? '' : 'opacity-40 pointer-events-none' }}">
                <button type="button"
                        data-slot="stop"
                        wire:click="stopAll"
                        class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs text-red-600 dark:text-red-400"
                        aria-label="Stop tutto">
                    <flux:icon name="stop-circle" class="h-6 w-6" />
                    <span>Stop</span>
                </button>
                <button type="button"
                        data-slot="volume"
                        wire:click="openVolume"
                        class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs"
                        aria-label="Volume">
                    <flux:icon name="speaker-wave" class="h-6 w-6" />
                    <span>Volume</span>
                </button>
                <button type="button" data-slot="new" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-lg text-xs font-semibold" aria-label="Nuovo contenuto"></button>
                <button type="button" data-slot="call" class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs" aria-label="Chiama"></button>
            </div>
        </nav>

        @if($showVolume)
            <flux:modal wire:model="showVolume" name="bottom-bar-volume" class="max-w-md">
                <div class="p-4">
                    <flux:heading size="lg" class="mb-4">Volume</flux:heading>
                    <livewire:dashboard.controls.volume-control :onesiBox="$onesiBox" wire:key="bottom-volume-{{ $onesiBox->id }}" />
                </div>
            </flux:modal>
        @endif
    @endif
</div>
