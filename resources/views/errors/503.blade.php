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
    <x-slot:styles>
        /* The sticker's monitor */
        :root {
            --terminal: #262c33;
            --prompt: #7ee05c;
            --glow: #86e3d4;
        }

        /* The sticker's own monitor, carried down into the page */
        .terminal {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.25rem 0.625rem;
            margin: 1.75rem 0 0;
            padding: 0.75rem 1rem;
            border: 1px solid rgb(134 227 212 / 0.45);
            border-radius: 0.5rem;
            background-color: var(--terminal);
            box-shadow:
                inset 0 0 18px rgb(134 227 212 / 0.12),
                0 0 24px rgb(134 227 212 / 0.12);
            font-family: var(--font-mono);
            font-size: 0.9375rem;
            line-height: 1.4;
            text-align: left;
        }

        .terminal .prompt {
            color: var(--prompt);
        }

        .terminal .cursor {
            display: inline-block;
            width: 0.6em;
            height: 1.1em;
            margin-left: 0.1em;
            background-color: var(--glow);
            vertical-align: text-bottom;
            animation: blink 1.1s steps(1) infinite;
        }

        @keyframes blink {
            50% {
                opacity: 0;
            }
        }
    </x-slot:styles>

    <x-slot:heading>Jaina&rsquo;s running the updates</x-slot:heading>

    <x-slot:lead>
        {{ config('app.name', 'Regrowth') }} is down for scheduled maintenance. Raid signups, loot and the roster are safe &ndash; nothing is lost while we work.
    </x-slot:lead>

    <x-slot:motif>
        <p class="terminal" role="status">
            <span class="prompt" aria-hidden="true">jaina@regrowth:~$</span>
            <span>
                @if ($retryMinutes)
                    Expected back in about {{ $retryMinutes }} {{ Str::plural('minute', $retryMinutes) }}
                @else
                    Back online soon
                @endif<span class="cursor" aria-hidden="true"></span>
            </span>
        </p>
    </x-slot:motif>

    <x-slot:primary-action>
        <a class="button button-primary" href="{{ url()->current() }}">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 10a6.5 6.5 0 1 1-1.9-4.6"/><path d="M16.5 3v3.5H13"/></svg>
            Try again
        </a>
    </x-slot:primary-action>
</x-error-page>
