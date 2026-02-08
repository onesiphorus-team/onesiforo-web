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
            }, 1000);
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
    class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6"
>
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

    <div class="flex items-center gap-3">
        {{-- Mute toggle --}}
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

        {{-- Visual volume bar --}}
        <div class="flex-1 relative">
            <div class="h-2 rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div
                    class="h-2 rounded-full transition-all duration-300"
                    :class="isMuted ? 'bg-zinc-400 dark:bg-zinc-500' : 'bg-zinc-800 dark:bg-zinc-200'"
                    :style="'width: ' + volume + '%'"
                ></div>
            </div>
        </div>

        {{-- Percentage label --}}
        <flux:text class="text-sm font-medium w-12 text-right tabular-nums">
            <span x-text="isMuted ? '{{ __('Muto') }}' : volume + '%'"></span>
        </flux:text>

        {{-- Dropdown trigger --}}
        <flux:dropdown position="bottom" align="end">
            <flux:button
                variant="subtle"
                size="sm"
                icon="adjustments-vertical"
                :disabled="!$this->canControl || !$this->isOnline"
                tooltip="Regola volume"
            />

            <flux:menu class="w-72 p-4" keep-open>
                <flux:heading size="sm" class="mb-3">Regola volume</flux:heading>

                {{-- Preset buttons grid --}}
                <div class="grid grid-cols-3 gap-2 mb-3">
                    @foreach ($volumeLevels as $level)
                        @if ($level > 0)
                            <flux:button
                                size="sm"
                                :variant="$this->nearestPreset === $level ? 'primary' : 'outline'"
                                wire:click="setVolume({{ $level }})"
                                wire:loading.attr="disabled"
                                wire:target="setVolume,setSliderVolume"
                                class="w-full"
                            >
                                {{ $level }}%
                            </flux:button>
                        @endif
                    @endforeach
                </div>

                <flux:separator class="my-3" variant="subtle" />

                {{-- Slider section --}}
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-on:click="nudge(-5)"
                        :disabled="!$wire.canControl || !$wire.isOnline || volume <= 0"
                        class="p-1 rounded text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
                        class="w-full h-2 rounded-lg appearance-none cursor-pointer bg-zinc-200 dark:bg-zinc-600 disabled:opacity-50 disabled:cursor-not-allowed [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-6 [&::-webkit-slider-thumb]:h-6 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-zinc-800 dark:[&::-webkit-slider-thumb]:bg-zinc-200 [&::-webkit-slider-thumb]:shadow-md [&::-moz-range-thumb]:w-6 [&::-moz-range-thumb]:h-6 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-zinc-800 [&::-moz-range-thumb]:border-0 dark:[&::-moz-range-thumb]:bg-zinc-200 [&::-moz-range-thumb]:shadow-md"
                    />
                    <button
                        type="button"
                        x-on:click="nudge(5)"
                        :disabled="!$wire.canControl || !$wire.isOnline || volume >= 100"
                        class="p-1 rounded text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <flux:icon name="plus" class="w-4 h-4" />
                    </button>
                </div>

                <flux:text class="text-center text-zinc-500 dark:text-zinc-400 text-sm mt-2">
                    <span x-text="volume + '%'"></span>
                </flux:text>
            </flux:menu>
        </flux:dropdown>
    </div>

    @error('level')
        <flux:text class="text-red-500 text-sm mt-2 text-center">{{ $message }}</flux:text>
    @enderror
</div>
