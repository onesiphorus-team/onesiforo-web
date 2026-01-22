<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="video-camera" class="w-5 h-5 inline-block mr-2" />
        Riproduzione Video
    </flux:heading>

    <form wire:submit="playVideo" class="space-y-4">
        <div>
            <flux:input
                wire:model="videoUrl"
                label="URL Video da jw.org"
                type="url"
                inputmode="url"
                autocomplete="off"
                autocapitalize="off"
                placeholder="https://www.jw.org/it/..."
                description="Copia e incolla l'URL della pagina jw.org contenente il video."
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
            <span wire:loading.remove wire:target="playVideo">Riproduci Video</span>
            <span wire:loading wire:target="playVideo">Invio in corso...</span>
        </flux:button>
    </form>
</div>
