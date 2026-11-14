<div>
    @if($this->canControl)
        @php
            $volumeLabel = $this->currentVolume === 0 ? __('Muto') : $this->currentVolume.'%';
            $volumeIcon = $this->currentVolume === 0 ? 'speaker-x-mark' : 'speaker-wave';
            $callLabel = $this->isInCall ? 'Termina' : 'Zoom';
            $callIcon = $this->isInCall ? 'phone-x-mark' : 'video-camera';
            $tile = 'flex items-center justify-center rounded-xl border font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500';
            $neutralTile = $tile.' border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700';
            $stopTile = $tile.' border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20';
            $callTile = $this->isInCall ? $stopTile : $neutralTile;
            $newTile = $tile.' border-indigo-500 bg-indigo-500 text-white shadow-sm hover:bg-indigo-600 dark:border-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400';
        @endphp

        {{-- Desktop command bar: stop | volume, call | new content. Colours follow the page palette: red to stop, indigo for the main action, grey for the rest. --}}
        <div class="hidden items-stretch gap-3 rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm md:flex dark:border-zinc-700 dark:bg-zinc-800 {{ $this->isOnline ? '' : 'pointer-events-none opacity-40' }}" role="toolbar" aria-label="Comandi del box">
            <button type="button" data-slot="stop" wire:click="stopAll" class="{{ $stopTile }} h-12 gap-2 px-5 text-sm" aria-label="Ferma tutto">
                <flux:icon name="stop-circle" class="size-5" />
                <span>Stop</span>
            </button>

            <span class="w-px self-stretch bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>

            <button type="button" data-slot="volume" wire:click="openVolume" class="{{ $neutralTile }} h-12 gap-2 px-5 text-sm" aria-label="Volume {{ $this->currentVolume }}%">
                <flux:icon name="{{ $volumeIcon }}" class="size-5" />
                <span>Volume <span class="tabular-nums">{{ $volumeLabel }}</span></span>
            </button>
            <button type="button" data-slot="call" wire:click="callAction" class="{{ $callTile }} h-12 gap-2 px-5 text-sm" aria-label="{{ $this->isInCall ? 'Termina chiamata Zoom' : 'Avvia chiamata Zoom' }}">
                <flux:icon name="{{ $callIcon }}" class="size-5" />
                <span>{{ $callLabel }}</span>
            </button>

            <button type="button" data-slot="new" x-on:click="$dispatch('open-quick-play')" class="{{ $newTile }} ml-auto h-12 gap-2 px-6 text-sm font-semibold" aria-label="Scegli cosa mostrare sul box">
                <flux:icon name="play-circle" class="size-5" />
                <span>Scegli cosa mostrare</span>
            </button>
        </div>

        {{-- Mobile command bar, fixed at the bottom of the screen --}}
        <nav class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden dark:border-zinc-700 dark:bg-zinc-900/95"
             aria-label="Comandi del box">
            <div class="grid grid-cols-5 gap-2 px-3 py-2 {{ $this->isOnline ? '' : 'pointer-events-none opacity-40' }}">
                <button type="button" data-slot="stop" wire:click="stopAll" class="{{ $stopTile }} min-h-14 flex-col gap-0.5 text-[11px]" aria-label="Ferma tutto">
                    <flux:icon name="stop-circle" class="size-6" />
                    <span>Stop</span>
                </button>
                <button type="button" data-slot="volume" wire:click="openVolume" class="{{ $neutralTile }} min-h-14 flex-col gap-0.5 text-[11px]" aria-label="Volume {{ $this->currentVolume }}%">
                    <flux:icon name="{{ $volumeIcon }}" class="size-6" />
                    <span class="tabular-nums">{{ $volumeLabel }}</span>
                </button>
                <button type="button" data-slot="new" x-on:click="$dispatch('open-quick-play')" class="{{ $newTile }} col-span-2 min-h-14 flex-col gap-0.5 text-xs font-semibold" aria-label="Scegli cosa mostrare sul box">
                    <flux:icon name="play-circle" class="size-6" />
                    <span>Mostra</span>
                </button>
                <button type="button" data-slot="call" wire:click="callAction" class="{{ $callTile }} min-h-14 flex-col gap-0.5 text-[11px]" aria-label="{{ $this->isInCall ? 'Termina chiamata Zoom' : 'Avvia chiamata Zoom' }}">
                    <flux:icon name="{{ $callIcon }}" class="size-6" />
                    <span>{{ $callLabel }}</span>
                </button>
            </div>
        </nav>

        <flux:modal wire:model="showVolume" name="bottom-bar-volume" class="md:min-w-96">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Volume</flux:heading>
                    <flux:text class="mt-1">Scegli quanto forte si sente il box.</flux:text>
                </div>
                <livewire:dashboard.controls.volume-control :onesiBox="$onesiBox" wire:key="bottom-volume-{{ $onesiBox->id }}" />
            </div>
        </flux:modal>
    @endif
</div>
