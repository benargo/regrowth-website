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

    $texture = $inline('images/classic/bg_brown_texture.jpg', 'image/jpeg');
    $masthead = $inline('images/classic/bg_masthead_md.webp', 'image/webp');
    $frizQuadrata = $inline('fonts/friz-quadrata-regular-os.woff', 'font/woff');

    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? ($exception->getHeaders()['Retry-After'] ?? null)
        : null;
    $retryMinutes = is_numeric($retryAfter) ? max(1, (int) ceil($retryAfter / 60)) : null;

    $discordInviteUrl = config('guild.discord_invite_url');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Down for Maintenance &middot; {{ config('app.name', 'Regrowth') }}</title>

        @if (config('app.fonts.typekit_url'))
            <link rel="stylesheet" href="{{ config('app.fonts.typekit_url') }}">
        @endif

        <link rel="icon" type="image/webp" href="{{ asset('images/guild_emblem.webp') }}">

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

            :root {
                --brown: #231a13;
                --brown-100: #f5f0eb;
                --brown-300: #d1c3b4;
                --brown-400: #b8a48f;
                --brown-500: #9a826a;
                --brown-600: #7a6249;
                --brown-700: #5c472f;
                --brown-800: #4a3d32;
                --brown-900: #372d22;
                --amber-100: #fef3c6;
                --amber-300: #ffd230;
                --amber-400: #ffb900;
                --amber-500: #fe9a00;
                --amber-600: #e17100;
                --amber-700: #bb4d00;
                --font-sans: "Semplicita Pro", "Open Sans", ui-sans-serif, system-ui, sans-serif;
                --font-serif: "Friz Quadrata", "Garamond", ui-serif, serif;
            }

            *,
            *::before,
            *::after {
                box-sizing: border-box;
            }

            html,
            body {
                margin: 0;
                min-height: 100%;
            }

            body {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                min-height: 100dvh;
                background-color: var(--brown-800);
                @if ($texture)
                    background-image: url("{{ $texture }}");
                @endif
                background-position: center -200px;
                background-repeat: repeat;
                color: var(--brown-100);
                font-family: var(--font-sans);
                line-height: 1.6;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                overflow-x: clip;
            }

            /* Masthead strip, mirroring SharedHeader's border-ink-600 / border-b-8 */
            .masthead {
                position: relative;
                height: 13rem;
                border-bottom: 8px solid var(--amber-600);
                background-color: var(--brown-900);
                @if ($masthead)
                    background-image: url("{{ $masthead }}");
                @endif
                background-position: center;
                background-size: cover;
            }

            .masthead::after {
                content: "";
                position: absolute;
                inset: 0;
                background: linear-gradient(to bottom, rgb(0 0 0 / 0.35), rgb(0 0 0 / 0.65));
            }

            .emblem {
                position: absolute;
                left: 50%;
                bottom: 0;
                z-index: 1;
                width: 8.5rem;
                height: 8.5rem;
                transform: translate(-50%, 50%);
                filter: drop-shadow(0 6px 18px rgb(0 0 0 / 0.75));
                animation: settle 1.2s cubic-bezier(0.2, 0.8, 0.2, 1) both;
            }

            main {
                flex: 1;
                display: flex;
                justify-content: center;
                padding: 6.5rem 1rem 3rem;
            }

            .panel {
                width: 100%;
                max-width: 40rem;
                text-align: center;
                animation: fade-in-up 0.6s ease-out 0.2s both;
            }

            .eyebrow {
                margin: 0 0 0.75rem;
                color: var(--amber-400);
                font-size: 0.8125rem;
                font-weight: 600;
                letter-spacing: 0.25em;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                padding-bottom: 0.15em;
                background: linear-gradient(to bottom, var(--amber-100), var(--amber-300));
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                font-family: var(--font-serif);
                font-size: clamp(2.25rem, 7vw, 3.25rem);
                font-weight: normal;
                line-height: 1.15;
                filter: drop-shadow(0 2px 10px rgb(0 0 0 / 0.8));
            }

            .subtitle {
                display: block;
                margin-top: 0.5rem;
                background: linear-gradient(to bottom, var(--amber-300), var(--amber-500));
                -webkit-background-clip: text;
                background-clip: text;
                font-size: clamp(1.05rem, 3vw, 1.3rem);
            }

            .divider {
                display: flex;
                align-items: center;
                gap: 0.875rem;
                margin: 2rem auto;
                max-width: 22rem;
                color: var(--amber-600);
            }

            .divider::before,
            .divider::after {
                content: "";
                flex: 1;
                height: 1px;
                background: linear-gradient(to var(--direction, right), transparent, currentColor);
            }

            .divider::after {
                --direction: left;
            }

            .divider svg {
                width: 0.875rem;
                height: 0.875rem;
                flex-shrink: 0;
            }

            .card {
                padding: 2rem 1.75rem;
                border: 1px solid rgb(225 113 0 / 0.3);
                border-radius: 0.5rem;
                background-color: rgb(0 0 0 / 0.25);
                box-shadow:
                    inset 0 1px 0 rgb(255 210 48 / 0.06),
                    0 10px 30px rgb(0 0 0 / 0.35);
            }

            .card p {
                margin: 0;
            }

            .lead {
                color: var(--brown-100);
                font-size: 1.125rem;
            }

            .lead + .muted {
                margin-top: 1rem;
            }

            .muted {
                color: var(--brown-300);
                font-size: 0.9375rem;
            }

            .card .status {
                display: inline-flex;
                align-items: center;
                gap: 0.6rem;
                margin-top: 1.5rem;
                padding: 0.4rem 0.9rem;
                border: 1px solid rgb(254 154 0 / 0.25);
                border-radius: 999px;
                background-color: rgb(35 26 19 / 0.6);
                color: var(--amber-300);
                font-size: 0.8125rem;
                font-weight: 600;
                letter-spacing: 0.04em;
            }

            .status .dot {
                width: 0.5rem;
                height: 0.5rem;
                border-radius: 50%;
                background-color: var(--amber-400);
                box-shadow: 0 0 0 0 rgb(255 185 0 / 0.6);
                animation: pulse 2s ease-out infinite;
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
                padding: 0.75rem 1.5rem;
                border: 1px solid transparent;
                border-radius: 0.25rem;
                font: inherit;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                transition:
                    background-color 150ms ease,
                    border-color 150ms ease,
                    color 150ms ease;
            }

            .button svg {
                width: 1rem;
                height: 1rem;
            }

            .button-primary {
                background-color: var(--amber-600);
                color: #fff;
            }

            .button-primary:hover {
                background-color: var(--amber-700);
            }

            .button-secondary {
                border-color: rgb(209 195 180 / 0.35);
                background-color: rgb(0 0 0 / 0.2);
                color: var(--brown-100);
            }

            .button-secondary:hover {
                border-color: var(--brown-300);
                background-color: rgb(0 0 0 / 0.35);
            }

            .button:focus-visible {
                outline: 2px solid var(--amber-300);
                outline-offset: 3px;
            }

            footer {
                padding: 1.5rem 1rem 2rem;
                color: var(--brown-400);
                font-size: 0.8125rem;
                text-align: center;
            }

            footer span {
                font-family: var(--font-serif);
                color: var(--brown-300);
            }

            @keyframes fade-in-up {
                from {
                    opacity: 0;
                    transform: translateY(0.75rem);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes settle {
                from {
                    opacity: 0;
                    transform: translate(-50%, 35%) scale(0.92);
                }
                to {
                    opacity: 1;
                    transform: translate(-50%, 50%) scale(1);
                }
            }

            @keyframes pulse {
                0% {
                    box-shadow: 0 0 0 0 rgb(255 185 0 / 0.55);
                }
                70% {
                    box-shadow: 0 0 0 0.6rem rgb(255 185 0 / 0);
                }
                100% {
                    box-shadow: 0 0 0 0 rgb(255 185 0 / 0);
                }
            }

            @media (min-width: 768px) {
                .masthead {
                    height: 17rem;
                }

                .emblem {
                    width: 10rem;
                    height: 10rem;
                }

                main {
                    padding-top: 7.5rem;
                }

                .card {
                    padding: 2.25rem 2.5rem;
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
        <header class="masthead">
            <img class="emblem" src="{{ asset('images/guild_emblem.webp') }}" alt="{{ config('app.name', 'Regrowth') }} guild emblem" width="160" height="160">
        </header>

        <main>
            <div class="panel">
                <p class="eyebrow">Error 503 &middot; Down for maintenance</p>

                <h1>
                    The Guild Hall Is Closed
                    <span class="subtitle">Our officers are making repairs</span>
                </h1>

                <div class="divider" aria-hidden="true">
                    <svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 0 10 6 16 8 10 10 8 16 6 10 0 8 6 6Z"/></svg>
                </div>

                <section class="card">
                    <p class="lead">
                        We&rsquo;re carrying out some scheduled maintenance on the site. Grab a drink at the inn, repair your gear and we&rsquo;ll have the doors open again shortly.
                    </p>
                    <p class="muted">
                        Raid signups, loot and the roster are all safe &ndash; nothing has been lost while we work.
                    </p>

                    <p class="status" role="status">
                        <span class="dot" aria-hidden="true"></span>
                        @if ($retryMinutes)
                            Expected back in about {{ $retryMinutes }} {{ Str::plural('minute', $retryMinutes) }}
                        @else
                            Back online soon
                        @endif
                    </p>
                </section>

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

        <footer>
            <span>{{ config('app.name', 'Regrowth') }}</span>
            @if (config('services.blizzard.realm.name'))
                &middot; {{ config('services.blizzard.realm.name') }}
            @endif
        </footer>
    </body>
</html>
