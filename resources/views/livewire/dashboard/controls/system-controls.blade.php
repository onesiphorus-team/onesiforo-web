<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="cog-6-tooth" class="w-5 h-5 inline-block mr-2" />
        Controlli Sistema
    </flux:heading>

    @if($this->isAdmin)
        <div class="space-y-4">
            {{-- Confirmation Alerts --}}
            @if($showRestartServiceConfirm)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Conferma riavvio servizio</flux:callout.heading>
                    <flux:callout.text>
                        Sei sicuro di voler riavviare il servizio OnesiBox? L'applicazione si riavvierà senza riavviare il dispositivo.
                    </flux:callout.text>
                </flux:callout>

                <div class="flex gap-2">
                    <flux:button wire:click="restartService" class="flex-1 !bg-amber-500 hover:!bg-amber-600 !text-white" wire:loading.attr="disabled" icon="arrow-path-rounded-square">
                        <span wire:loading.remove wire:target="restartService">Conferma Riavvio Servizio</span>
                        <span wire:loading wire:target="restartService">Invio...</span>
                    </flux:button>

                    <flux:button variant="subtle" wire:click="cancelRestartService">
                        Annulla
                    </flux:button>
                </div>
            @elseif($showRebootConfirm)
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading>Attenzione: Riavvio dispositivo</flux:callout.heading>
                    <flux:callout.text>
                        Sei sicuro di voler riavviare il dispositivo? La connessione verrà interrotta per alcuni minuti. Tutte le attività in corso verranno terminate.
                    </flux:callout.text>
                </flux:callout>

                <div class="flex gap-2">
                    <flux:button variant="danger" wire:click="reboot" class="flex-1" wire:loading.attr="disabled" icon="arrow-path">
                        <span wire:loading.remove wire:target="reboot">Conferma Riavvio Dispositivo</span>
                        <span wire:loading wire:target="reboot">Invio...</span>
                    </flux:button>

                    <flux:button variant="subtle" wire:click="cancelReboot">
                        Annulla
                    </flux:button>
                </div>
            @else
                {{-- Buttons side by side on desktop, stacked on mobile --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:button wire:click="confirmRestartService" class="!bg-amber-500 hover:!bg-amber-600 !text-white" icon="arrow-path-rounded-square">
                        Riavvia Servizio
                    </flux:button>

                    <flux:button variant="danger" wire:click="confirmReboot" icon="arrow-path">
                        Riavvia Dispositivo
                    </flux:button>
                </div>

                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                    <strong>Riavvia Servizio:</strong> riavvia solo l'applicazione OnesiBox.
                    <strong>Riavvia Dispositivo:</strong> riavvia completamente il sistema.
                </flux:text>
            @endif
        </div>
    @else
        <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
            Solo gli amministratori possono accedere ai controlli di sistema.
        </flux:text>
    @endif
</div>
