<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="phone" class="w-5 h-5 inline-block mr-2" />
        Chiamata Zoom
    </flux:heading>

    <form wire:submit="startCall" class="space-y-4">
        <flux:input
            wire:model="meetingId"
            label="Meeting ID"
            placeholder="123456789"
        />

        <flux:input
            wire:model="password"
            label="Password (opzionale)"
            type="password"
        />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                <span wire:loading.remove>Avvia</span>
                <span wire:loading>Avvio...</span>
            </flux:button>

            <flux:button type="button" variant="danger" wire:click="endCall" wire:loading.attr="disabled">
                <flux:icon name="phone-x-mark" class="w-4 h-4" />
            </flux:button>
        </div>
    </form>
</div>
