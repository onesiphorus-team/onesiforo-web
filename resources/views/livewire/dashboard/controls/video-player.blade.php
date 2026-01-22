<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="video-camera" class="w-5 h-5 inline-block mr-2" />
        Riproduzione Video
    </flux:heading>

    <form wire:submit="playVideo" class="space-y-4">
        <flux:input
            wire:model="videoUrl"
            label="URL Video"
            type="url"
            placeholder="https://example.com/video.mp4"
        />

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Riproduci</span>
            <span wire:loading>Invio in corso...</span>
        </flux:button>
    </form>
</div>
