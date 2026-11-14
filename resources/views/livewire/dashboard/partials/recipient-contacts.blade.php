@php
    $callRow = 'flex min-h-12 items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2 hover:bg-zinc-50 active:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-700/40';
@endphp
<div class="space-y-3">
    <div class="flex items-center gap-3">
        <flux:icon name="user" class="size-5 shrink-0 text-zinc-400" />
        <span class="font-medium text-zinc-900 dark:text-white">{{ $recipient->full_name }}</span>
    </div>

    @if($recipient->full_address)
        <div class="flex items-start gap-3">
            <flux:icon name="map-pin" class="size-5 shrink-0 text-zinc-400" />
            <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $recipient->full_address }}</span>
        </div>
    @endif

    @if($recipient->phone)
        <a href="tel:{{ preg_replace('/[^\d+]/', '', $recipient->phone) }}" class="{{ $callRow }}">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                <flux:icon name="phone" variant="micro" class="size-4" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-medium text-zinc-900 dark:text-white">Chiama {{ $recipient->first_name ?? $recipient->full_name }}</span>
                <span class="block text-sm tabular-nums text-zinc-500 dark:text-zinc-400">{{ $recipient->phone }}</span>
            </span>
        </a>
    @endif

    @if($recipient->emergency_contacts && count($recipient->emergency_contacts) > 0)
        <div class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <p class="text-sm font-semibold text-zinc-600 dark:text-zinc-300">Contatti di emergenza</p>
            @foreach($recipient->emergency_contacts as $contact)
                <a href="tel:{{ preg_replace('/[^\d+]/', '', (string) ($contact['phone'] ?? '')) }}" class="{{ $callRow }}">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                        <flux:icon name="phone" variant="micro" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $contact['name'] }}
                            @if(isset($contact['relationship']))
                                <span class="font-normal text-zinc-500 dark:text-zinc-400">· {{ $contact['relationship'] }}</span>
                            @endif
                        </span>
                        <span class="block text-sm tabular-nums text-zinc-500 dark:text-zinc-400">{{ $contact['phone'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
