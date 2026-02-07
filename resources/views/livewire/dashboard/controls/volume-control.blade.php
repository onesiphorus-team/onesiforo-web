<div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6">
    <div class="flex items-center gap-2 mb-4">
        <flux:icon name="speaker-wave" class="w-5 h-5 text-zinc-500 dark:text-zinc-400" />
        <flux:heading size="lg">Volume</flux:heading>
    </div>

    @if (!$this->isOnline)
        <flux:callout icon="wifi" class="mb-4">
            <flux:callout.text>
                Il controllo volume è disponibile solo quando il dispositivo è online.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if (!$this->canControl)
        <flux:callout icon="lock-closed" class="mb-4">
            <flux:callout.text>
                Non hai i permessi per controllare il volume di questo dispositivo.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap gap-2 justify-center">
        @foreach ($volumeLevels as $level)
            <flux:button
                wire:click="setVolume({{ $level }})"
                wire:loading.attr="disabled"
                wire:target="setVolume"
                :variant="$this->currentVolume === $level ? 'primary' : 'outline'"
                :disabled="!$this->canControl || !$this->isOnline"
                class="min-w-[60px]"
            >
                @if ($level === 0)
                    <flux:icon name="speaker-x-mark" class="w-4 h-4" />
                @else
                    {{ $level }}%
                @endif
            </flux:button>
        @endforeach
    </div>

    @error('level')
        <flux:text class="text-red-500 text-sm mt-2">{{ $message }}</flux:text>
    @enderror

    <flux:text class="text-center text-zinc-500 dark:text-zinc-400 text-sm mt-3">
        Volume attuale: {{ $this->currentVolume === 0 ? __('Muto') : $this->currentVolume . '%' }}
    </flux:text>
</div>
