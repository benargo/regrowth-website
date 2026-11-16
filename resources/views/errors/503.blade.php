@php
    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? ($exception->getHeaders()['Retry-After'] ?? null)
        : null;
    $retryMinutes = is_numeric($retryAfter) ? max(1, (int) ceil($retryAfter / 60)) : null;
@endphp
<x-error-page
    title="Down for maintenance"
    sticker="images/sticker_jaina_maintenance_503.webp"
    sticker-alt="Jaina the Samoyed in a headset, typing sudo apt-get update at a computer"
    sticker-width="1709"
    sticker-height="1611"
>
    <x-slot:heading>Jaina&rsquo;s running the updates</x-slot:heading>

    <x-slot:lead>
        {{ config('app.name', 'Regrowth') }} is down for scheduled maintenance. Raid signups, loot and the roster are safe &ndash; nothing is lost while we work.
    </x-slot:lead>

    <x-slot:motif>
        <x-error-page.terminal>
            <span>
                <x-error-page.terminal.prompt />
                <span>
                    @if ($retryMinutes)
                        Expected back in about {{ $retryMinutes }} {{ Str::plural('minute', $retryMinutes) }}
                    @else
                        Back online soon
                    @endif<x-error-page.terminal.cursor class="ml-[0.1em]" />
                </span>
            </span>
        </x-error-page.terminal>
    </x-slot:motif>

    <x-slot:primary-action>
        <x-error-page.button :href="url()->current()">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 10a6.5 6.5 0 1 1-1.9-4.6"/><path d="M16.5 3v3.5H13"/></svg>
            Try again
        </x-error-page.button>
    </x-slot:primary-action>
</x-error-page>
