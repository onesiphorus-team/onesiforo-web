<div class="space-y-4">
    <div>
        <flux:heading size="lg">Audio da un link</flux:heading>
        <flux:text class="mt-1">Se hai il link di un audio di jw.org (ad esempio ricevuto su WhatsApp), incollalo qui: il box lo mostrerà subito.</flux:text>
    </div>

    <form wire:submit="playAudio" class="space-y-4">
        <div>
            <flux:input
                wire:model="audioUrl"
                label="Incolla il link"
                type="url"
                inputmode="url"
                autocomplete="off"
                autocapitalize="off"
                placeholder="https://www.jw.org/it/..."
                description="Tieni premuto nel riquadro e scegli «Incolla»."
                class="[&_input]:text-base [&_input]:py-3"
            />
        </div>

        <flux:button
            type="submit"
            variant="primary"
            color="indigo"
            class="w-full py-4 text-base font-medium"
            icon="play"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="playAudio">Mostra sul box</span>
            <span wire:loading wire:target="playAudio">Invio in corso...</span>
        </flux:button>
    </form>
</div>
