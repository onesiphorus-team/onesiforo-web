<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="video-camera" class="w-5 h-5 inline-block mr-2" />
        Chiamata Zoom
    </flux:heading>

    <form wire:submit="startCall" class="space-y-4">
        <div>
            <flux:input
                wire:model="zoomUrl"
                label="Link Zoom"
                type="url"
                inputmode="url"
                autocomplete="off"
                autocapitalize="off"
                placeholder="https://us05web.zoom.us/j/..."
                description="Incolla il link di invito Zoom ricevuto."
                class="[&_input]:text-base [&_input]:py-3"
            />
        </div>

        <flux:callout variant="info" icon="information-circle" class="text-sm">
            <flux:callout.text>
                La connessione avviene automaticamente con il nome <strong>Rosa Iannascoli</strong>.
            </flux:callout.text>
        </flux:callout>

        <flux:button
            type="submit"
            variant="primary"
            class="w-full py-4 text-base font-medium"
            icon="phone"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="startCall">Avvia Chiamata Zoom</span>
            <span wire:loading wire:target="startCall">Connessione...</span>
        </flux:button>
    </form>
</div>
