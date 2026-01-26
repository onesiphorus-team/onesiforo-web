<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="cog-6-tooth" class="w-5 h-5 inline-block mr-2" />
        Controlli Sistema
    </flux:heading>

    @if($this->isAdmin)
        <div class="space-y-4">
            {{-- Restart Service --}}
            @if($showRestartServiceConfirm)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Conferma riavvio servizio</flux:callout.heading>
                    <flux:callout.text>
                        Sei sicuro di voler riavviare il servizio OnesiBox? L'applicazione si riavvierà senza riavviare il dispositivo.
                    </flux:callout.text>
                </flux:callout>

                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="restartService" class="flex-1" wire:loading.attr="disabled" icon="arrow-path-rounded-square">
                        <span wire:loading.remove wire:target="restartService">Conferma Riavvio Servizio</span>
                        <span wire:loading wire:target="restartService">Invio...</span>
                    </flux:button>

                    <flux:button variant="subtle" wire:click="cancelRestartService">
                        Annulla
                    </flux:button>
                </div>
            @else
                <flux:button variant="subtle" wire:click="confirmRestartService" class="w-full" icon="arrow-path-rounded-square">
                    Riavvia Servizio OnesiBox
                </flux:button>
            @endif

            {{-- Reboot Device --}}
            @if($showRebootConfirm)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Conferma riavvio dispositivo</flux:callout.heading>
                    <flux:callout.text>
                        Sei sicuro di voler riavviare il dispositivo? La connessione verrà interrotta temporaneamente.
                    </flux:callout.text>
                </flux:callout>

                <div class="flex gap-2">
                    <flux:button variant="danger" wire:click="reboot" class="flex-1" wire:loading.attr="disabled" icon="arrow-path">
                        <span wire:loading.remove wire:target="reboot">Conferma Riavvio</span>
                        <span wire:loading wire:target="reboot">Invio...</span>
                    </flux:button>

                    <flux:button variant="subtle" wire:click="cancelReboot">
                        Annulla
                    </flux:button>
                </div>
            @else
                <flux:button variant="filled" wire:click="confirmReboot" class="w-full" icon="arrow-path">
                    Riavvia Dispositivo
                </flux:button>
            @endif
        </div>
    @else
        <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
            Solo gli amministratori possono accedere ai controlli di sistema.
        </flux:text>
    @endif
</div>
