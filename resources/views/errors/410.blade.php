@php
    $requestedPath = request()->decodedPath();
    $requestedPath = $requestedPath === '/' ? '/' : '/'.Str::limit($requestedPath, 60);
@endphp
<x-error-page
    title="Page gone"
    sticker="images/sticker_jaina_410.webp"
    sticker-alt="Jaina the Samoyed looking up at a door boarded shut with planks and a chain"
    sticker-width="1680"
    sticker-height="1834"
    discord-label="Ask on Discord"
>
    <x-slot:styles>
        /* The sticker's door */
        :root {
            --oak: #8a5a33;
            --oak-light: #a8743f;
            --oak-dark: #4a2f1a;
            --nail: #241a12;
            --chalk: #f4ead8;
        }

        /* One of the sticker's planks, nailed over wherever the visitor landed */
        .plank {
            position: relative;
            display: inline-grid;
            grid-template-areas:
                "label"
                "path";
            max-width: 100%;
            margin: 1.75rem 0 0;
            padding: 0.75rem 2.25rem;
            border: 1px solid var(--oak-dark);
            border-radius: 4px 2px 5px 3px / 3px 5px 2px 4px;
            background-color: var(--oak);
            background-image:
                radial-gradient(circle at 1rem 50%, var(--nail) 0.2rem, transparent 0.25rem),
                radial-gradient(circle at calc(100% - 1rem) 50%, var(--nail) 0.2rem, transparent 0.25rem),
                repeating-linear-gradient(
                    178deg,
                    transparent 0 0.5rem,
                    rgb(74 47 26 / 0.22) 0.5rem 0.5625rem,
                    transparent 0.5625rem 0.875rem
                ),
                linear-gradient(to bottom, var(--oak-light), var(--oak) 55%, var(--oak-dark));
            box-shadow:
                inset 0 1px 0 rgb(255 255 255 / 0.12),
                0 10px 24px rgb(0 0 0 / 0.35);
            color: var(--chalk);
            text-align: left;
            text-shadow: 0 1px 1px rgb(36 26 18 / 0.6);
            transform: rotate(-1.5deg);
        }

        .plank-label {
            grid-area: label;
            font-family: var(--font-serif);
            font-size: 1.0625rem;
            line-height: 1.2;
        }

        .plank-path {
            grid-area: path;
            font-family: var(--font-mono);
            font-size: 0.875rem;
            line-height: 1.4;
            overflow-wrap: anywhere;
            text-decoration: line-through;
            text-decoration-thickness: 2px;
        }
    </x-slot:styles>

    <x-slot:heading>Jaina found this door boarded up</x-slot:heading>

    <x-slot:lead>
        This page existed once, but it&rsquo;s been removed for good. Old raid plans are cleared out a month after the raid. Head home to find what&rsquo;s current for raids, loot and the roster.
    </x-slot:lead>

    <x-slot:motif>
        <p class="plank">
            <span class="plank-label">Closed for good</span>
            <span class="plank-path">{{ $requestedPath }}</span>
        </p>
    </x-slot:motif>
</x-error-page>
