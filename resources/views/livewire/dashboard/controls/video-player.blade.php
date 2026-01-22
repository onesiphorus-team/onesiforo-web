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
                placeholder="https://www.jw.org/it/.../video/#it/mediaitems/..."
                description="Apri il video su jw.org, poi copia l'URL dalla barra degli indirizzi del browser."
            />
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                <flux:icon name="play" class="w-4 h-4" />
                <span wire:loading.remove>Riproduci</span>
                <span wire:loading>Invio...</span>
            </flux:button>

            <flux:button type="button" variant="danger" wire:click="stopPlayback" wire:loading.attr="disabled">
                <flux:icon name="stop" class="w-4 h-4" />
            </flux:button>
        </div>
    </form>
</div>
