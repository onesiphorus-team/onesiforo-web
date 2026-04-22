<div data-hero-state="{{ $state }}"
     class="rounded-lg border p-4 {{ $state === 'offline' ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700' }}"
     aria-live="polite">
    @if($state === 'idle')
        <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Online · In attesa</flux:text>
        </div>
        <flux:text class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
            Ultimo contatto: {{ $onesiBox->last_seen_at?->diffForHumans() ?? '—' }}
        </flux:text>
    @elseif($state === 'media')
        @php
            $type = strtoupper((string) $onesiBox->current_media_type);
            $host = $onesiBox->current_media_url
                ? (parse_url($onesiBox->current_media_url, PHP_URL_HOST) ?? $onesiBox->current_media_url)
                : null;
            $title = $onesiBox->current_media_title ?: $host;
            $pos = $onesiBox->current_media_position;
            $dur = $onesiBox->current_media_duration;
            $pct = ($pos !== null && $dur !== null && $dur > 0) ? min(100, (int) round($pos / $dur * 100)) : null;
        @endphp

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold tracking-wide bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                ▶ {{ $type }}
            </span>
        </div>
        <flux:heading size="lg" class="mt-2 line-clamp-2 break-words">{{ $title }}</flux:heading>
        @if($host)
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Fonte: {{ $host }}</flux:text>
        @endif

        @if($pct !== null)
            <div class="mt-3 flex items-center gap-2">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}">
                    <div class="h-full bg-green-500" style="width: {{ $pct }}%"></div>
                </div>
                <flux:text class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ gmdate($dur >= 3600 ? 'H:i:s' : 'i:s', (int) $pos) }} / {{ gmdate($dur >= 3600 ? 'H:i:s' : 'i:s', (int) $dur) }}
                </flux:text>
            </div>
        @endif
    @elseif($state === 'call')
        <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <flux:icon name="phone" class="h-5 w-5" />
            <flux:text class="font-medium">Chiamata in corso</flux:text>
        </div>
        <flux:heading size="lg" class="mt-2">Meeting {{ $onesiBox->current_meeting_id }}</flux:heading>
        @if($onesiBox->current_meeting_joined_at)
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                Iniziata {{ $onesiBox->current_meeting_joined_at->diffForHumans() }}
            </flux:text>
        @endif
    @elseif($state === 'offline')
        <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300">
            <flux:icon name="exclamation-triangle" class="h-5 w-5" />
            <flux:text class="font-medium">Dispositivo offline</flux:text>
        </div>
        <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            Ultimo contatto: {{ $onesiBox->last_seen_at?->diffForHumans() ?? '—' }}
        </flux:text>
        @if($onesiBox->app_version)
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">v{{ $onesiBox->app_version }}</flux:text>
        @endif
    @endif
</div>
