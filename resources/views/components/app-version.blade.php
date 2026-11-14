{{-- The running version, from the git tag of the deployed checkout --}}
<span {{ $attributes->class('text-xs text-zinc-400 dark:text-zinc-500') }} data-slot="app-version" title="{{ __('Versione di Onesiforo') }}">
    {{ \App\Support\Version::get() }}
</span>
