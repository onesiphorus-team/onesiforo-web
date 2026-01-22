<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="cog-6-tooth" class="w-5 h-5 inline-block mr-2" />
        Controlli Sistema
    </flux:heading>

    @if($this->isAdmin)
        <div class="space-y-4">
            @if($showRebootConfirm)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Conferma riavvio</flux:callout.heading>
                    <flux:callout.text>
                        Sei sicuro di voler riavviare il dispositivo? La connessione verrà interrotta temporaneamente.
                    </flux:callout.text>
                </flux:callout>

                <div class="flex gap-2">
                    <flux:button variant="danger" wire:click="reboot" class="flex-1" wire:loading.attr="disabled">
                        <flux:icon name="arrow-path" class="w-4 h-4" />
                        <span wire:loading.remove>Conferma Riavvio</span>
                        <span wire:loading>Invio...</span>
                    </flux:button>

                    <flux:button variant="subtle" wire:click="cancelReboot">
                        Annulla
                    </flux:button>
                </div>
            @else
                <flux:button variant="filled" wire:click="confirmReboot" class="w-full">
                    <flux:icon name="arrow-path" class="w-4 h-4" />
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
