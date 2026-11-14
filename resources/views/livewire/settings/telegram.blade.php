<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">Telegram</flux:heading>

    <x-settings.layout heading="Telegram" subheading="Ricevi su Telegram gli avvisi sui box che segui.">
        @if($this->isConnected)
            <div class="space-y-4">
                <flux:callout icon="check-circle" color="green">
                    <flux:callout.heading>Telegram è collegato</flux:callout.heading>
                    <flux:callout.text>Gli avvisi arrivano nella chat con il bot di Onesiforo.</flux:callout.text>
                </flux:callout>
                <flux:button wire:click="disconnect" wire:confirm="Non riceverai più gli avvisi su Telegram. Vuoi scollegarlo?" variant="danger">
                    Scollega Telegram
                </flux:button>
            </div>
        @elseif($this->linkUrl)
            {{-- Waiting for the user to press Start in the bot: check every few seconds --}}
            <div class="space-y-4" wire:poll.3s>
                <ol class="list-decimal space-y-2 ps-5 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>Tocca <strong>Apri Telegram</strong>: si apre la chat con il bot di Onesiforo.</li>
                    <li>Nel bot premi <strong>Avvia</strong> (o «Start»).</li>
                    <li>Torna qui: la pagina si aggiorna da sola.</li>
                </ol>
                <flux:button :href="$this->linkUrl" target="_blank" variant="primary" icon="paper-airplane">Apri Telegram</flux:button>
                <flux:text class="flex items-center gap-2 text-sm">
                    <flux:icon.loading class="size-4" /> In attesa che tu prema Avvia nel bot…
                </flux:text>
            </div>
        @else
            <div class="space-y-4">
                <flux:text>Collegando Telegram riceverai, ad esempio, l'avviso delle adunanze in arrivo per i box che segui.</flux:text>
                <flux:button wire:click="startLinking" variant="primary" icon="link">Collega Telegram</flux:button>
            </div>
        @endif
    </x-settings.layout>
</section>
