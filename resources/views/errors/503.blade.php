@php
    /*
     * Self-contained on purpose: during a deploy the Vite manifest and build
     * output may be missing or mid-rebuild, and `artisan down --render` may
     * pre-render this view, so nothing here depends on Vite, the database or
     * the session. Theme assets are inlined straight from resources/.
     */
    $inline = static function (string $path, string $mime): ?string {
        $fullPath = resource_path($path);

        return is_readable($fullPath)
            ? "data:{$mime};base64,".base64_encode(file_get_contents($fullPath))
            : null;
    };

    $camelot = $inline('images/bg_camelot.webp', 'image/webp');
    $frizQuadrata = $inline('fonts/friz-quadrata-regular-os.woff', 'font/woff');

    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? ($exception->getHeaders()['Retry-After'] ?? null)
        : null;
    $retryMinutes = is_numeric($retryAfter) ? max(1, (int) ceil($retryAfter / 60)) : null;

    $discordInviteUrl = config('guild.discord_invite_url');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#1b1a3a">
        <title>Down for maintenance &middot; {{ config('app.name', 'Regrowth') }}</title>

        @if (config('app.fonts.typekit_url'))
            <link rel="stylesheet" href="{{ config('app.fonts.typekit_url') }}">
        @endif

        <link rel="icon" type="image/webp" href="{{ asset('images/guild_emblem.webp') }}">
        <link rel="preload" as="image" href="{{ asset('images/sticker_jaina_maintenance_503.webp') }}">

        <style>
            @if ($frizQuadrata)
                @font-face {
                    font-family: "Friz Quadrata";
                    src: url("{{ $frizQuadrata }}") format("woff");
                    font-weight: normal;
                    font-style: normal;
                    font-display: swap;
                }
            @endif

            /* Palette sampled from the Camelot scene and the sticker's monitor */
            :root {
                --dusk: #1b1a3a;
                --slate: #34406b;
                --mist: #c9cce6;
                --snow: #eef0fb;
                --sunset: #ffd56a;
                --sunset-light: #ffe49c;
                --camel-200: #ffffd9;
                --terminal: #262c33;
                --prompt: #7ee05c;
                --glow: #86e3d4;
                --gutter: 1rem;
                --sticker-width: min(78vw, 24rem);
                --font-sans: "Semplicita Pro", "Open Sans", ui-sans-serif, system-ui, sans-serif;
                --font-serif: "Friz Quadrata", "Garamond", ui-serif, serif;
                --font-mono: ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace;
            }

            *,
            *::before,
            *::after {
                box-sizing: border-box;
            }

            html,
            body {
                margin: 0;
            }

            body {
                min-height: 100vh;
                min-height: 100dvh;
                background-color: var(--dusk);
                color: var(--snow);
                font-family: var(--font-sans);
                line-height: 1.6;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                overflow-x: clip;
            }

            /* Mirrors .bg-camelot from resources/css/app.css, with the image inlined */
            .bg-camelot {
                @if ($camelot)
                    background-image: url("{{ $camelot }}");
                @endif
                background-position: center;
                background-size: cover;
            }

            .scene {
                position: relative;
                height: clamp(17rem, 70vw, 24rem);
                background-color: var(--slate);
                background-position: 22% 40%;
            }

            /* Darken the sky behind the brand mark and melt the cliffs into the page */
            .scene::after {
                content: "";
                position: absolute;
                inset: 0;
                background:
                    linear-gradient(to bottom, rgb(27 26 58 / 0.55), transparent 28%),
                    linear-gradient(to bottom, transparent 45%, var(--dusk));
            }

            .brand {
                position: absolute;
                top: 1rem;
                left: var(--gutter);
                z-index: 1;
                display: flex;
                align-items: center;
                gap: 0.625rem;
                margin: 0;
                color: var(--camel-200);
                font-family: var(--font-serif);
                font-size: 1.25rem;
                font-weight: normal;
                line-height: 1;
                text-shadow: 0 1px 8px rgb(0 0 0 / 0.6);
            }

            .brand img {
                width: 2.75rem;
                height: 2.75rem;
                filter: drop-shadow(0 2px 6px rgb(0 0 0 / 0.6));
            }

            .stage {
                display: grid;
                grid-template-areas:
                    "sticker"
                    "copy";
                max-width: 80rem;
                margin: 0 auto;
                padding: 0 var(--gutter) 3rem;
            }

            .sticker {
                grid-area: sticker;
                position: relative;
                z-index: 1;
                justify-self: center;
                width: var(--sticker-width);
                margin: calc(var(--sticker-width) * -0.3) 0 0;
                transform: rotate(-3deg);
                animation: slap 0.7s cubic-bezier(0.3, 1.4, 0.5, 1) 0.15s both;
            }

            .sticker img {
                display: block;
                width: 100%;
                height: auto;
                filter: drop-shadow(0 18px 28px rgb(0 0 0 / 0.45));
            }

            .copy {
                grid-area: copy;
                max-width: 34rem;
                margin: 1.5rem auto 0;
                text-align: center;
            }

            h1 {
                margin: 0;
                padding-bottom: 0.1em;
                background: linear-gradient(to bottom, var(--camel-200), var(--sunset));
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                font-family: var(--font-serif);
                font-size: clamp(2.25rem, 9vw, 3.75rem);
                font-weight: normal;
                line-height: 1.05;
                text-wrap: balance;
            }

            .lead {
                margin: 1.25rem 0 0;
                color: var(--mist);
                font-size: 1.125rem;
                text-wrap: pretty;
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

            .actions {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.75rem;
                margin-top: 2rem;
            }

            .button {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.75rem 1.375rem;
                border: 1px solid transparent;
                border-radius: 0.375rem;
                font: inherit;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                transition:
                    background-color 150ms ease,
                    border-color 150ms ease;
            }

            .button svg {
                width: 1rem;
                height: 1rem;
            }

            .button-primary {
                background-color: var(--sunset);
                color: var(--dusk);
            }

            .button-primary:hover {
                background-color: var(--sunset-light);
            }

            .button-secondary {
                border-color: rgb(238 240 251 / 0.3);
                color: var(--snow);
            }

            .button-secondary:hover {
                border-color: rgb(238 240 251 / 0.6);
                background-color: rgb(238 240 251 / 0.06);
            }

            .button:focus-visible {
                outline: 2px solid var(--sunset);
                outline-offset: 3px;
            }

            @keyframes slap {
                0% {
                    opacity: 0;
                    transform: rotate(6deg) scale(1.18);
                }
                60% {
                    opacity: 1;
                }
                100% {
                    opacity: 1;
                    transform: rotate(-3deg) scale(1);
                }
            }

            @keyframes blink {
                50% {
                    opacity: 0;
                }
            }

            @media (min-width: 900px) {
                :root {
                    --gutter: clamp(1.5rem, 4vw, 3rem);
                    --sticker-width: min(36vw, 32rem);
                }

                .scene {
                    height: clamp(22rem, 54vh, 34rem);
                    background-position: center 40%;
                }

                .brand {
                    top: 1.5rem;
                }

                .stage {
                    grid-template-areas: "copy sticker";
                    grid-template-columns: minmax(0, 1fr) auto;
                    column-gap: 2rem;
                }

                .sticker {
                    margin-top: calc(var(--sticker-width) * -0.48);
                }

                .copy {
                    margin: 0;
                    padding-top: 0.5rem;
                    text-align: left;
                }

                .actions {
                    justify-content: flex-start;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    animation: none !important;
                    transition: none !important;
                }
            }
        </style>
    </head>
    <body>
        <header class="scene bg-camelot">
            <p class="brand">
                <img src="{{ asset('images/guild_emblem.webp') }}" alt="" width="44" height="44">
                {{ config('app.name', 'Regrowth') }}
            </p>
        </header>

        <main class="stage">
            <figure class="sticker">
                <img src="{{ asset('images/sticker_jaina_maintenance_503.webp') }}" alt="Jaina the Samoyed in a headset, typing sudo apt-get update at a computer" width="1709" height="1611">
            </figure>

            <div class="copy">
                <h1>Jaina&rsquo;s running the updates</h1>

                <p class="lead">
                    {{ config('app.name', 'Regrowth') }} is down for scheduled maintenance. Raid signups, loot and the roster are safe &ndash; nothing is lost while we work.
                </p>

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

                <div class="actions">
                    <a class="button button-primary" href="{{ url()->current() }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 10a6.5 6.5 0 1 1-1.9-4.6"/><path d="M16.5 3v3.5H13"/></svg>
                        Try again
                    </a>

                    @if ($discordInviteUrl)
                        <a class="button button-secondary" href="{{ $discordInviteUrl }}" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.3 4.4A19.8 19.8 0 0 0 15.4 3l-.6 1.3a18.4 18.4 0 0 0-5.6 0L8.6 3a19.7 19.7 0 0 0-4.9 1.5C.6 9.1-.3 13.6.1 18.1a19.9 19.9 0 0 0 6 3l1.3-2.1c-.7-.3-1.4-.6-2-1l.5-.4a14.2 14.2 0 0 0 12.2 0l.5.4c-.6.4-1.3.7-2 1l1.3 2.1a19.8 19.8 0 0 0 6-3c.5-5.2-.9-9.7-3.6-13.7ZM8 15.4c-1.2 0-2.2-1.1-2.2-2.4S6.8 10.6 8 10.6s2.2 1.1 2.2 2.4-1 2.4-2.2 2.4Zm8 0c-1.2 0-2.2-1.1-2.2-2.4s1-2.4 2.2-2.4 2.2 1.1 2.2 2.4-1 2.4-2.2 2.4Z"/></svg>
                            Join us on Discord
                        </a>
                    @endif
                </div>
            </div>
        </main>
    </body>
</html>
