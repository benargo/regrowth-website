@props([
    'title',
    'sticker',
    'stickerAlt',
    'stickerWidth',
    'stickerHeight',
    'discordLabel' => null,
    'homeAction' => true,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scheme-dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#1b1a3a">
        <title>{{ $title }} &middot; {{ config('app.name', 'Regrowth') }}</title>

        @if (config('app.fonts.typekit_url'))
            <link rel="stylesheet" href="{{ config('app.fonts.typekit_url') }}">
        @endif

        <link rel="icon" type="image/webp" href="{{ asset('images/guild_emblem.webp') }}">
        <link rel="preload" as="image" href="{{ asset($sticker) }}">

        @vite('resources/css/app.css')
    </head>
    <body class="min-h-dvh overflow-x-clip bg-camelot-dusk font-sans leading-[1.6] text-camelot-snow antialiased [--gutter:1rem] lg:[--gutter:clamp(1.5rem,4vw,3rem)]">
        <header class="bg-camelot relative h-[clamp(17rem,70vw,24rem)] bg-camelot-slate bg-position-[22%_40%]! lg:h-[clamp(22rem,54vh,34rem)] lg:bg-position-[center_40%]!">
            <div class="absolute inset-0 bg-linear-to-b from-camelot-dusk/55 to-transparent to-28%" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-linear-to-b from-transparent from-45% to-camelot-dusk" aria-hidden="true"></div>

            <p class="absolute top-4 left-(--gutter) z-10 flex items-center gap-2.5 font-serif text-xl leading-none text-camel-200 text-shadow-[0_1px_8px_rgb(0_0_0/0.6)] lg:top-6">
                <img class="size-11 drop-shadow-[0_2px_6px_rgb(0_0_0/0.6)]" src="{{ asset('images/guild_emblem.webp') }}" alt="" width="44" height="44">
                {{ config('app.name', 'Regrowth') }}
            </p>
        </header>

        <main class="mx-auto grid max-w-7xl px-(--gutter) pb-12 [--sticker-width:min(78vw,24rem)] lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-x-8 lg:[--sticker-width:min(36vw,32rem)]">
            <figure class="relative z-10 mt-[calc(var(--sticker-width)*-0.3)] w-(--sticker-width) -rotate-3 justify-self-center motion-safe:animate-sticker-slap lg:col-start-2 lg:row-start-1 lg:mt-[calc(var(--sticker-width)*-0.48)]">
                <img class="block h-auto w-full drop-shadow-[0_18px_28px_rgb(0_0_0/0.45)]" src="{{ asset($sticker) }}" alt="{{ $stickerAlt }}" width="{{ $stickerWidth }}" height="{{ $stickerHeight }}">
            </figure>

            <div class="mx-auto mt-6 max-w-136 text-center lg:col-start-1 lg:row-start-1 lg:m-0 lg:pt-2 lg:text-left">
                <h1 class="bg-linear-to-b from-camel-200 to-camelot-sunset bg-clip-text pb-[0.1em] font-serif text-[clamp(2.25rem,9vw,3.75rem)] leading-[1.05] text-balance text-transparent">{{ $heading }}</h1>

                <p class="mt-5 text-lg/[1.6] text-pretty text-camelot-mist">
                    {{ $lead }}
                </p>

                {{ $motif ?? '' }}

                @if (isset($primaryAction) || $homeAction || $discordLabel)
                    <div class="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
                        @isset($primaryAction)
                            {{ $primaryAction }}
                        @elseif ($homeAction)
                            <x-error-page.button :href="route('home')">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5 10 3.5l7 6"/><path d="M5 8v8.5h10V8"/><path d="M8.5 16.5v-4.5h3v4.5"/></svg>
                                Go to the home page
                            </x-error-page.button>
                        @endisset

                        @if ($discordLabel)
                            <x-error-page.button :href="route('discord')" variant="secondary" target="_blank" rel="nofollow noopener noreferrer">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.3 4.4A19.8 19.8 0 0 0 15.4 3l-.6 1.3a18.4 18.4 0 0 0-5.6 0L8.6 3a19.7 19.7 0 0 0-4.9 1.5C.6 9.1-.3 13.6.1 18.1a19.9 19.9 0 0 0 6 3l1.3-2.1c-.7-.3-1.4-.6-2-1l.5-.4a14.2 14.2 0 0 0 12.2 0l.5.4c-.6.4-1.3.7-2 1l1.3 2.1a19.8 19.8 0 0 0 6-3c.5-5.2-.9-9.7-3.6-13.7ZM8 15.4c-1.2 0-2.2-1.1-2.2-2.4S6.8 10.6 8 10.6s2.2 1.1 2.2 2.4-1 2.4-2.2 2.4Zm8 0c-1.2 0-2.2-1.1-2.2-2.4s1-2.4 2.2-2.4 2.2 1.1 2.2 2.4-1 2.4-2.2 2.4Z"/></svg>
                                {{ $discordLabel }}
                            </x-error-page.button>
                        @endif
                    </div>
                @endif
            </div>
        </main>
    </body>
</html>
