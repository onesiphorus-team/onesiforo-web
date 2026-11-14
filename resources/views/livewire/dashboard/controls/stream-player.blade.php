<div class="space-y-4">
    <div>
        <flux:heading size="lg">Programma di JW Stream</flux:heading>
        <flux:text class="mt-1">Per i programmi condivisi con un link di stream.jw.org (ad esempio un'assemblea): incolla il link e il box mostrerà i video uno dopo l'altro.</flux:text>
    </div>

    {{-- Form URL --}}
    <form wire:submit="playFromStart" class="space-y-4">
        <flux:input
            wire:model="url"
            label="Incolla il link"
            type="url"
            inputmode="url"
            autocomplete="off"
            autocapitalize="off"
            placeholder="https://stream.jw.org/XXXX-XXXX-XXXX-XXXX"
            :invalid="$errors->has('url')"
            class="[&_input]:py-3 [&_input]:text-base"
        />
        <flux:button
            type="submit"
            variant="primary"
            color="indigo"
            icon="play"
            class="w-full py-4 text-base font-medium"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="playFromStart">Mostra sul box</span>
            <span wire:loading wire:target="playFromStart">Invio...</span>
        </flux:button>
        @error('url')
            <flux:text class="text-red-600">{{ $message }}</flux:text>
        @enderror
    </form>

    {{-- Controlli --}}
    @if($lastOrdinalSent !== null)
        <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button
                wire:click="previous"
                :disabled="$lastOrdinalSent <= 1"
                icon="chevron-left"
                size="sm"
            >
                Precedente
            </flux:button>

            <flux:text class="font-medium">
                Video n. {{ $lastOrdinalSent }}
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
                'E110' => 'Il box non riesce a raggiungere JW Stream: controlla che sia collegato a Internet.',
                'E111' => 'Programma non caricato: il link potrebbe essere sbagliato o scaduto.',
                'E112' => 'Il programma è finito: questo era l\'ultimo video.',
                'E113' => 'Il video non è partito. Riprova tra poco; se succede ancora, avvisa chi gestisce il box.',
                default => 'Il box ha avuto un problema (codice ' . $errorCode . '). Riprova; se succede ancora, avvisa chi gestisce il box.',
            };
            $bannerIcon = match($errorCode) {
                'E112' => 'check-circle',
                'E113' => 'exclamation-triangle',
                default => 'x-circle',
            };
        @endphp

        <div>
            <flux:callout variant="{{ $bannerVariant }}" icon="{{ $bannerIcon }}">
                <flux:callout.heading>{{ $bannerMessage }}</flux:callout.heading>
                <x-slot name="actions">
                    <flux:button size="xs" wire:click="dismissError" variant="ghost">Chiudi</flux:button>
                </x-slot>
            </flux:callout>
        </div>
    @endif
</div>
