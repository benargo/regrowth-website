@php
    $requestedPath = request()->decodedPath();
    $requestedPath = $requestedPath === '/' ? '/' : '/'.Str::limit($requestedPath, 60);
@endphp
<x-error-page
    title="Page not found"
    sticker="images/sticker_jaina_404.webp"
    sticker-alt="Jaina the Samoyed frowning at a crumpled map marked “You are here”"
    sticker-width="1455"
    sticker-height="1511"
    discord-label="Ask on Discord"
>
    <x-slot:styles>
        /* The sticker's map */
        :root {
            --parchment: #e8dcc0;
            --parchment-edge: #c9b48f;
            --ink: #2a2218;
        }

        /* A scrap torn from the sticker's map, pinned to wherever the visitor landed */
        .map-scrap {
            display: inline-grid;
            grid-template-areas:
                "arrow label"
                "arrow path";
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            column-gap: 0.75rem;
            max-width: 100%;
            margin: 1.75rem 0 0;
            padding: 0.75rem 1.125rem 0.75rem 0.875rem;
            border: 1px solid var(--parchment-edge);
            border-radius: 3px 7px 4px 9px / 8px 3px 6px 4px;
            background-color: var(--parchment);
            background-image: linear-gradient(100deg, transparent 49%, rgb(42 34 24 / 0.08) 50%, transparent 51%);
            box-shadow: 0 10px 24px rgb(0 0 0 / 0.35);
            color: var(--ink);
            text-align: left;
            transform: rotate(1.5deg);
        }

        .map-scrap svg {
            grid-area: arrow;
            width: 1.75rem;
            height: 1.75rem;
        }

        .map-scrap-label {
            grid-area: label;
            font-family: var(--font-serif);
            font-size: 1.0625rem;
            line-height: 1.2;
        }

        .map-scrap-path {
            grid-area: path;
            font-family: var(--font-mono);
            font-size: 0.875rem;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }
    </x-slot:styles>

    <x-slot:heading>Jaina can&rsquo;t find this page</x-slot:heading>

    <x-slot:lead>
        There&rsquo;s nothing at this address. The link may be out of date, or the page has moved. Head home to find your way back to raids, loot and the roster.
    </x-slot:lead>

    <x-slot:motif>
        <p class="map-scrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 5 6 18"/><path d="M6 9v9h9"/></svg>
            <span class="map-scrap-label">You are here</span>
            <span class="map-scrap-path">{{ $requestedPath }}</span>
        </p>
    </x-slot:motif>

    <x-slot:primary-action>
        <a class="button button-primary" href="{{ route('home') }}">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5 10 3.5l7 6"/><path d="M5 8v8.5h10V8"/><path d="M8.5 16.5v-4.5h3v4.5"/></svg>
            Go to the home page
        </a>
    </x-slot:primary-action>
</x-error-page>
