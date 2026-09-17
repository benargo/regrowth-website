<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $page['props']['theme'] ?? \App\Enums\Theme::default()->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title data-inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        @if (config('app.fonts.typekit_url'))
            <link rel="stylesheet" href="{{ config('app.fonts.typekit_url') }}">
        @endif

        <!-- Open Graph -->
        <meta property="og:title" content="{{ config('app.name', 'Laravel') }}">
        <meta property="og:description" content="The homepage of {{ config('app.name', 'Laravel') }} on {{ config('services.blizzard.realm.name') }}.">
        <meta property="og:image" content="{{ asset('images/og_image.webp') }}">
        <meta property="og:url" content="{{ url('/') }}">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead

        <link rel="icon" type="image/webp" rel="noopener" target="_blank" href="{{ asset('images/guild_emblem.webp') }}" />
    </head>
    <body class="font-sans antialiased overflow-x-clip">
        @inertia

        <!-- Wowhead Tooltips -->
        <script>const whTooltips = {colorLinks: true, iconizeLinks: true, renameLinks: false};</script>
        <script src="https://wow.zamimg.com/js/tooltips.js"></script>
    </body>
</html>
