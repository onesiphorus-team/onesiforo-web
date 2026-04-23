<div
    x-data="{
        volume: $wire.entangle('sliderVolume'),
        previousVolume: $wire.entangle('sliderVolume'),
        timeout: null,

        get isMuted() { return this.volume === 0; },

        toggleMute() {
            if (this.isMuted) {
                this.volume = this.previousVolume > 0 ? this.previousVolume : 80;
            } else {
                this.previousVolume = this.volume;
                this.volume = 0;
            }
            this.sendVolume(this.volume);
        },

        updateVolume(val) {
            clearTimeout(this.timeout);
            this.timeout = setTimeout(() => {
                this.sendVolume(parseInt(val));
            }, 500);
        },

        sendVolume(val) {
            clearTimeout(this.timeout);
            $wire.setSliderVolume(val);
        },

        nudge(delta) {
            this.volume = Math.max(0, Math.min(100, this.volume + delta));
            this.updateVolume(this.volume);
        }
    }"
    class="space-y-5"
>
    @if (!$this->isOnline)
        <flux:callout icon="wifi">
            <flux:callout.text>
                Il controllo volume è disponibile solo quando il dispositivo è online.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if (!$this->canControl)
        <flux:callout icon="lock-closed">
            <flux:callout.text>
                Non hai i permessi per controllare il volume di questo dispositivo.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Livello attuale: icona mute + barra visuale + percentuale --}}
    <div class="flex items-center gap-4">
        <flux:button
            square
            variant="subtle"
            size="sm"
            x-on:click="toggleMute()"
            :disabled="!$this->canControl || !$this->isOnline"
            tooltip="Muto / Riattiva"
        >
            <flux:icon x-show="!isMuted" name="speaker-wave" class="w-5 h-5" />
            <flux:icon x-show="isMuted" name="speaker-x-mark" class="w-5 h-5" x-cloak />
        </flux:button>

        <div class="flex-1">
            <div class="h-2 rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div
                    class="h-2 rounded-full transition-all duration-300"
                    :class="isMuted ? 'bg-zinc-400 dark:bg-zinc-500' : 'bg-zinc-800 dark:bg-zinc-200'"
                    :style="'width: ' + volume + '%'"
                ></div>
            </div>
        </div>

        <flux:text class="text-sm font-medium w-14 text-right tabular-nums">
            <span x-text="isMuted ? '{{ __('Muto') }}' : volume + '%'"></span>
        </flux:text>
    </div>

    {{-- Preset buttons --}}
    <div class="grid grid-cols-3 gap-2">
        @foreach ($volumeLevels as $level)
            <flux:button
                size="sm"
                :variant="$this->nearestPreset === $level ? 'primary' : 'outline'"
                wire:click="setVolume({{ $level }})"
                wire:loading.attr="disabled"
                wire:target="setVolume,setSliderVolume"
                :disabled="!$this->canControl || !$this->isOnline"
                class="w-full"
            >
                {{ $level }}%
            </flux:button>
        @endforeach
    </div>

    {{-- Slider con nudge buttons --}}
    <div class="flex items-center gap-3">
        <button
            type="button"
            x-on:click="nudge(-5)"
            :disabled="!$wire.canControl || !$wire.isOnline || volume <= 0"
            class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            aria-label="Diminuisci volume di 5"
        >
            <flux:icon name="minus" class="w-4 h-4" />
        </button>
        <input
            type="range"
            min="0"
            max="100"
            step="5"
            x-model.number="volume"
            x-on:input="updateVolume($event.target.value)"
            :disabled="!$wire.canControl || !$wire.isOnline"
            class="flex-1 h-2 rounded-lg appearance-none cursor-pointer bg-zinc-200 dark:bg-zinc-600 disabled:opacity-50 disabled:cursor-not-allowed [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-6 [&::-webkit-slider-thumb]:h-6 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-zinc-800 dark:[&::-webkit-slider-thumb]:bg-zinc-200 [&::-webkit-slider-thumb]:shadow-md [&::-moz-range-thumb]:w-6 [&::-moz-range-thumb]:h-6 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-zinc-800 [&::-moz-range-thumb]:border-0 dark:[&::-moz-range-thumb]:bg-zinc-200 [&::-moz-range-thumb]:shadow-md"
            aria-label="Regola volume"
        />
        <button
            type="button"
            x-on:click="nudge(5)"
            :disabled="!$wire.canControl || !$wire.isOnline || volume >= 100"
            class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            aria-label="Aumenta volume di 5"
        >
            <flux:icon name="plus" class="w-4 h-4" />
        </button>
    </div>

    @error('level')
        <flux:text class="text-red-500 text-sm text-center">{{ $message }}</flux:text>
    @enderror
</div>
