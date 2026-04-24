<div wire:poll.10s class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:icon name="queue-list" class="w-5 h-5 text-zinc-500 dark:text-zinc-400" />
            <flux:heading size="lg">Coda Comandi</flux:heading>
            @if($this->pendingCommands->count() > 0)
                <flux:badge color="blue" size="sm">{{ $this->pendingCommands->count() }}</flux:badge>
            @endif
        </div>

        @if($this->canControl && $this->pendingCommands->count() > 1)
            <flux:button
                wire:click="confirmCancelAll"
                variant="subtle"
                size="sm"
                icon="trash"
            >
                Annulla tutti
            </flux:button>
        @endif
    </div>

    @if($this->pendingCommands->isEmpty())
        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
            <flux:icon name="check-circle" class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>Nessun comando in coda</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($this->pendingCommands as $command)
                <div
                    wire:key="command-{{ $command->uuid }}"
                    class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg"
                >
                    <div class="flex items-center gap-3">
                        <flux:badge :color="$command->type->getColor()" size="sm">
                            {{ $command->type->getLabel() }}
                        </flux:badge>
                        <div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $command->created_at->diffForHumans() }}
                                @if($command->priority <= 2)
                                    <flux:badge color="amber" size="sm" class="ml-1">Alta priorità</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($this->canControl)
                        <flux:button
                            wire:click="cancelCommand('{{ $command->uuid }}')"
                            wire:loading.attr="disabled"
                            variant="subtle"
                            size="sm"
                            icon="x-mark"
                        >
                            <span class="sr-only">Annulla</span>
                        </flux:button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Cancel All Confirmation Modal --}}
    @if($showCancelAllConfirmation)
        <flux:modal wire:model="showCancelAllConfirmation" name="cancel-all-modal">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Conferma annullamento</flux:heading>
                <flux:text class="mb-6">
                    Vuoi annullare tutti i {{ $this->pendingCommands->count() }} comandi in coda?
                    Questa azione non può essere annullata.
                </flux:text>
                <div class="flex gap-3 justify-end">
                    <flux:button
                        wire:click="cancelCancelAll"
                        variant="subtle"
                    >
                        No, mantieni
                    </flux:button>
                    <flux:button
                        wire:click="cancelAll"
                        variant="danger"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="cancelAll">Sì, annulla tutti</span>
                        <span wire:loading wire:target="cancelAll">Annullamento...</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
