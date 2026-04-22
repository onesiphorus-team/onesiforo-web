<div class="space-y-3">
    <div class="flex items-center gap-3">
        <flux:icon name="user" class="h-5 w-5 text-zinc-400" />
        <flux:text>{{ $recipient->full_name }}</flux:text>
    </div>

    @if($recipient->phone)
        <div class="flex items-center gap-3">
            <flux:icon name="phone" class="h-5 w-5 text-zinc-400" />
            <a href="tel:{{ $recipient->phone }}" class="text-blue-600 hover:underline dark:text-blue-400">
                {{ $recipient->phone }}
            </a>
        </div>
    @endif

    @if($recipient->full_address)
        <div class="flex items-center gap-3">
            <flux:icon name="map-pin" class="h-5 w-5 text-zinc-400" />
            <flux:text>{{ $recipient->full_address }}</flux:text>
        </div>
    @endif

    @if($recipient->emergency_contacts && count($recipient->emergency_contacts) > 0)
        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:text class="mb-2 text-sm font-semibold text-zinc-600 dark:text-zinc-300">
                Contatti di emergenza
            </flux:text>
            @foreach($recipient->emergency_contacts as $contact)
                <div class="mb-2 flex items-center gap-3">
                    <flux:icon name="exclamation-triangle" class="h-5 w-5 text-amber-500" />
                    <flux:text>
                        {{ $contact['name'] }}
                        @if(isset($contact['relationship'])) ({{ $contact['relationship'] }}) @endif
                        - {{ $contact['phone'] }}
                    </flux:text>
                </div>
            @endforeach
        </div>
    @endif
</div>
