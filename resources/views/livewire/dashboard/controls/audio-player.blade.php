<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="speaker-wave" class="w-5 h-5 inline-block mr-2" />
        Riproduzione Audio
    </flux:heading>

    <form wire:submit="playAudio" class="space-y-4">
        <div>
            <flux:input
                wire:model="audioUrl"
                label="URL Audio da jw.org"
                type="url"
                inputmode="url"
                autocomplete="off"
                autocapitalize="off"
                placeholder="https://www.jw.org/it/..."
                description="Copia e incolla l'URL della pagina jw.org contenente l'audio."
                class="[&_input]:text-base [&_input]:py-3"
            />
        </div>

        <flux:button
            type="submit"
            variant="primary"
            class="w-full py-4 text-base font-medium"
            icon="play"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="playAudio">Riproduci Audio</span>
            <span wire:loading wire:target="playAudio">Invio in corso...</span>
        </flux:button>
    </form>
</div>
