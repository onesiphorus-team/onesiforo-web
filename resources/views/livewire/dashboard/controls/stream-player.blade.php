<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="play-circle" class="w-5 h-5 inline-block mr-2" />
        Stream Playlist (JW Stream)
    </flux:heading>

    {{-- Form URL --}}
    <form wire:submit="playFromStart" class="space-y-4">
        <div class="flex gap-2">
            <flux:input
                wire:model="url"
                placeholder="https://stream.jw.org/XXXX-XXXX-XXXX-XXXX"
                :invalid="$errors->has('url')"
                class="flex-1"
            />
            <flux:button
                type="submit"
                variant="primary"
                icon="play"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="playFromStart">Avvia playlist</span>
                <span wire:loading wire:target="playFromStart">Invio...</span>
            </flux:button>
        </div>
        @error('url')
            <flux:text class="text-red-600">{{ $message }}</flux:text>
        @enderror
    </form>

    {{-- Controlli --}}
    @if($lastOrdinalSent !== null)
        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button
                wire:click="previous"
                :disabled="$lastOrdinalSent <= 1"
                icon="chevron-left"
                size="sm"
            >
                Precedente
            </flux:button>

            <flux:text class="font-medium">
                Video corrente: {{ $lastOrdinalSent }}
            </flux:text>

            <flux:button
                wire:click="next"
                :disabled="$reachedEnd"
                icon-trailing="chevron-right"
                size="sm"
            >
                Successivo
            </flux:button>

            <flux:button
                wire:click="stop"
                variant="danger"
                icon="stop"
                size="sm"
            >
                Stop
            </flux:button>
        </div>
    @endif

    {{-- Banner errore --}}
    @if($errorCode !== null)
        @php
            $bannerVariant = match($errorCode) {
                'E112' => 'success',
                'E113' => 'warning',
                default => 'danger',
            };
            $bannerMessage = match($errorCode) {
                'E110' => 'Impossibile raggiungere JW Stream. Verifica la connessione del dispositivo.',
                'E111' => 'Playlist non caricata. L\'URL potrebbe essere errato o scaduto.',
                'E112' => 'Ultimo video della playlist raggiunto.',
                'E113' => 'Impossibile avviare il video. Il sito potrebbe essere cambiato — riprova o contatta supporto.',
                default => 'Errore sul dispositivo (codice: ' . $errorCode . ').',
            };
            $bannerIcon = match($errorCode) {
                'E112' => 'check-circle',
                'E113' => 'exclamation-triangle',
                default => 'x-circle',
            };
        @endphp

        <div class="mt-4">
            <flux:callout variant="{{ $bannerVariant }}" icon="{{ $bannerIcon }}" class="mt-4">
                <flux:callout.heading>{{ $bannerMessage }}</flux:callout.heading>
                <x-slot name="actions">
                    <flux:button size="xs" wire:click="dismissError" variant="ghost">Chiudi</flux:button>
                </x-slot>
            </flux:callout>
        </div>
    @endif
</div>
