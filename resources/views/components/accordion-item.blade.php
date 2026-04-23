@props([
    'title' => null,
    'open' => false,
])

<details
    class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800"
    @if($open) open @endif
    x-data
    x-init="if (window.matchMedia('(min-width: 768px)').matches) $el.setAttribute('open', '')"
>
    <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
        <span>{{ $title }}</span>
        <flux:icon name="chevron-down" class="chevron-toggle h-4 w-4 transition-transform group-open:rotate-180" />
    </summary>
    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
        {{ $slot }}
    </div>
</details>
