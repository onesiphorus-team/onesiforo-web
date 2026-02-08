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
                wire:target="setVolume,setSliderVolume"
                :variant="$this->nearestPreset === $level ? 'primary' : 'outline'"
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

    <div
        x-data="{
            volume: $wire.entangle('sliderVolume'),
            timeout: null,
            updateVolume(val) {
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => {
                    $wire.setSliderVolume(parseInt(val));
                }, 1000);
            },
            nudge(delta) {
                this.volume = Math.max(0, Math.min(100, this.volume + delta));
                this.updateVolume(this.volume);
            }
        }"
        class="mt-4 px-2"
    >
        <div class="flex items-center gap-3">
            <button
                type="button"
                x-on:click="nudge(-5)"
                :disabled="!$wire.canControl || !$wire.isOnline || volume <= 0"
                class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <flux:icon name="speaker-x-mark" class="w-5 h-5" />
            </button>
            <input
                type="range"
                min="0"
                max="100"
                step="5"
                x-model.number="volume"
                x-on:input="updateVolume($event.target.value)"
                :disabled="!$wire.canControl || !$wire.isOnline"
                class="w-full h-2 rounded-lg appearance-none cursor-pointer bg-zinc-200 dark:bg-zinc-600 disabled:opacity-50 disabled:cursor-not-allowed [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-6 [&::-webkit-slider-thumb]:h-6 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-zinc-800 dark:[&::-webkit-slider-thumb]:bg-zinc-200 [&::-webkit-slider-thumb]:shadow-md [&::-moz-range-thumb]:w-6 [&::-moz-range-thumb]:h-6 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-zinc-800 [&::-moz-range-thumb]:border-0 dark:[&::-moz-range-thumb]:bg-zinc-200 [&::-moz-range-thumb]:shadow-md"
            />
            <button
                type="button"
                x-on:click="nudge(5)"
                :disabled="!$wire.canControl || !$wire.isOnline || volume >= 100"
                class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <flux:icon name="speaker-wave" class="w-5 h-5" />
            </button>
        </div>
        <flux:text class="text-center text-zinc-500 dark:text-zinc-400 text-sm mt-2">
            <span x-text="volume === 0 ? '{{ __('Muto') }}' : volume + '%'"></span>
        </flux:text>
    </div>

    @error('level')
        <flux:text class="text-red-500 text-sm mt-2 text-center">{{ $message }}</flux:text>
    @enderror
</div>
