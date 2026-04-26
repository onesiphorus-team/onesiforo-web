<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700"
     wire:poll.15s="checkCommandStatus">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="document-text" class="w-5 h-5 inline-block mr-2" />
        Log di Sistema
    </flux:heading>

    @if(!$this->isAdmin)
        <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
            Solo gli amministratori possono visualizzare i log di sistema.
        </flux:text>
    @elseif(!$this->isOnline)
        <flux:callout icon="wifi">
            <flux:callout.text>
                I log sono disponibili solo quando il dispositivo è online.
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="space-y-4">
            {{-- Request Form --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <flux:input
                        wire:model="lines"
                        type="number"
                        min="10"
                        max="500"
                        placeholder="Numero righe"
                        label="Righe da recuperare"
                        :disabled="$isLoading"
                    />
                    @error('lines')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-end gap-2">
                    <flux:button
                        wire:click="requestLogs"
                        wire:loading.attr="disabled"
                        :disabled="$isLoading"
                        variant="primary"
                        icon="arrow-down-tray"
                    >
                        @if($isLoading)
                            Caricamento...
                        @else
                            Richiedi Log
                        @endif
                    </flux:button>

                    @if($logs)
                        <flux:button
                            wire:click="clearLogs"
                            variant="subtle"
                            icon="x-mark"
                        >
                            Cancella
                        </flux:button>
                    @endif
                </div>
            </div>

            {{-- Loading State --}}
            @if($isLoading)
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-zinc-500"></div>
                    <span class="ml-3 text-zinc-500 dark:text-zinc-400">
                        Recupero log in corso...
                    </span>
                </div>
            @endif

            {{-- Logs Display --}}
            @if($logs && !$isLoading)
                <div class="relative">
                    <div class="absolute top-2 right-2 z-10">
                        <flux:button
                            size="xs"
                            variant="subtle"
                            icon="clipboard-document"
                            x-on:click="navigator.clipboard.writeText($refs.logsContent.textContent); $flux.toast('Log copiati negli appunti')"
                        >
                            Copia
                        </flux:button>
                    </div>

                    <div
                        x-ref="logsContent"
                        class="bg-zinc-900 text-green-400 font-mono text-xs p-4 rounded-lg overflow-auto max-h-96 whitespace-pre-wrap"
                    >{{ $logs }}</div>

                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 text-right">
                        {{ strlen($logs) }} caratteri
                    </div>
                </div>
            @endif

            @if(!$logs && !$isLoading)
                <div class="text-center py-6 text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="document-text" class="w-10 h-10 mx-auto mb-2 opacity-50" />
                    <p class="text-sm">Clicca "Richiedi Log" per visualizzare i log dell'applicazione.</p>
                </div>
            @endif
        </div>
    @endif
</div>
