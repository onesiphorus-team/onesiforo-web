<div class="rounded-xl border-2 border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950/30 p-4 sm:p-6">
    @if (!$showConfirmation)
        <flux:button
            wire:click="confirmStop"
            variant="danger"
            class="w-full py-4 text-lg font-semibold"
            icon="stop-circle"
        >
            Interrompi Tutte le Riproduzioni
        </flux:button>
    @else
        <div class="space-y-4">
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Conferma Interruzione</flux:callout.heading>
                <flux:callout.text>
                    Questa azione interrompe qualsiasi riproduzione audio, video o chiamata Zoom in corso sul dispositivo.
                </flux:callout.text>
            </flux:callout>

            <div class="flex flex-col sm:flex-row gap-3">
                <flux:button
                    wire:click="stopAll"
                    variant="danger"
                    class="flex-1 py-3"
                    icon="stop"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="stopAll">Conferma Interruzione</span>
                    <span wire:loading wire:target="stopAll">Invio...</span>
                </flux:button>
                <flux:button
                    wire:click="cancelStop"
                    variant="subtle"
                    class="flex-1 py-3"
                >
                    Annulla
                </flux:button>
            </div>
        </div>
    @endif
</div>
