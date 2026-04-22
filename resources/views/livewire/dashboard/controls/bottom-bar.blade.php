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
                <button type="button"
                        data-slot="new"
                        wire:click="openNew"
                        class="flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-lg bg-indigo-600 text-xs font-semibold text-white dark:bg-indigo-500"
                        aria-label="Nuovo contenuto">
                    <flux:icon name="plus-circle" class="h-6 w-6" />
                    <span>Nuovo</span>
                </button>

                <button type="button"
                        data-slot="call"
                        wire:click="callAction"
                        class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs {{ $this->isInCall ? 'text-red-600 dark:text-red-400' : '' }}"
                        aria-label="{{ $this->isInCall ? 'Termina chiamata' : 'Avvia chiamata' }}">
                    <flux:icon name="{{ $this->isInCall ? 'phone-x-mark' : 'phone' }}" class="h-6 w-6" />
                    <span>{{ $this->isInCall ? 'Termina' : 'Chiama' }}</span>
                </button>
            </div>
        </nav>

        <flux:modal wire:model="showVolume" name="bottom-bar-volume" class="max-w-md">
            <div class="p-4">
                <flux:heading size="lg" class="mb-4">Volume</flux:heading>
                <livewire:dashboard.controls.volume-control :onesiBox="$onesiBox" wire:key="bottom-volume-{{ $onesiBox->id }}" />
            </div>
        </flux:modal>
    @endif
</div>
