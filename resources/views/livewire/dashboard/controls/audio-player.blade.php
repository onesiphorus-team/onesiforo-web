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
                placeholder="https://www.jw.org/it/.../audio/#it/mediaitems/..."
                description="Apri l'audio su jw.org, poi copia l'URL dalla barra degli indirizzi del browser."
            />
        </div>

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Riproduci Audio</span>
            <span wire:loading>Invio in corso...</span>
        </flux:button>
    </form>
</div>
