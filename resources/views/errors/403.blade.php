@php
    $requestedPath = request()->decodedPath();
    $requestedPath = $requestedPath === '/' ? '/' : '/'.Str::limit($requestedPath, 60);
@endphp
<x-error-page
    title="Access denied"
    sticker="images/sticker_jaina_403.webp"
    sticker-alt="Jaina the Samoyed sitting in a cave mouth, telling an armoured orc “I can’t let you in”"
    sticker-width="1649"
    sticker-height="1511"
    discord-label="Ask an officer on Discord"
>
    <x-slot:styles>
        /* The sticker's cave */
        :root {
            --granite: #6a615b;
            --granite-light: #857b74;
            --granite-dark: #3b3532;
            --carving: #2a2522;
            --carving-highlight: rgb(255 255 255 / 0.18);
        }

        /* A slab of the sticker's cave rock, carved with wherever the visitor tried to go */
        .slab {
            display: inline-grid;
            grid-template-areas:
                "lock label"
                "lock path";
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            column-gap: 0.75rem;
            max-width: 100%;
            margin: 1.75rem 0 0;
            padding: 0.875rem 1.25rem 0.875rem 1rem;
            border: 1px solid var(--granite-dark);
            border-radius: 14px 6px 11px 4px / 5px 12px 6px 10px;
            background-color: var(--granite);
            background-image: linear-gradient(160deg, var(--granite-light), var(--granite) 45%, var(--granite-dark));
            box-shadow:
                inset 0 1px 0 rgb(255 255 255 / 0.14),
                inset 0 -3px 0 rgb(0 0 0 / 0.18),
                0 10px 24px rgb(0 0 0 / 0.35);
            color: var(--carving);
            text-align: left;
            text-shadow: 0 1px 0 var(--carving-highlight);
            transform: rotate(-1deg);
        }

        .slab svg {
            grid-area: lock;
            width: 1.75rem;
            height: 1.75rem;
            filter: drop-shadow(0 1px 0 var(--carving-highlight));
        }

        .slab-label {
            grid-area: label;
            font-family: var(--font-serif);
            font-size: 1.0625rem;
            line-height: 1.2;
        }

        .slab-path {
            grid-area: path;
            font-family: var(--font-mono);
            font-size: 0.875rem;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }
    </x-slot:styles>

    <x-slot:heading>Jaina can&rsquo;t let you in</x-slot:heading>

    <x-slot:lead>
        Your account doesn&rsquo;t have permission to open this page. Some pages are only for certain ranks or officers. If you think you should have access, ask an officer on Discord.
    </x-slot:lead>

    <x-slot:motif>
        <p class="slab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="1.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/></svg>
            <span class="slab-label">No entry</span>
            <span class="slab-path">{{ $requestedPath }}</span>
        </p>
    </x-slot:motif>

    <x-slot:primary-action>
        <a class="button button-primary" href="{{ route('home') }}">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5 10 3.5l7 6"/><path d="M5 8v8.5h10V8"/><path d="M8.5 16.5v-4.5h3v4.5"/></svg>
            Go to the home page
        </a>
    </x-slot:primary-action>
</x-error-page>
