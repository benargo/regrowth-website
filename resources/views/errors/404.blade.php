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
    <x-slot:heading>Jaina can&rsquo;t find this page</x-slot:heading>

    <x-slot:lead>
        There&rsquo;s nothing at this address. The link may be out of date, or the page has moved. Head home to find your way back to raids, loot and the roster.
    </x-slot:lead>

    <x-slot:motif>
        {{-- A scrap torn from the sticker's map, pinned to wherever the visitor landed --}}
        <p class="mt-7 inline-grid max-w-full rotate-[1.5deg] grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 rounded-[3px_7px_4px_9px/8px_3px_6px_4px] border border-[#c9b48f] bg-[#e8dcc0] bg-[linear-gradient(100deg,transparent_49%,rgb(42_34_24/0.08)_50%,transparent_51%)] py-3 pr-4.5 pl-3.5 text-left text-[#2a2218] shadow-[0_10px_24px_rgb(0_0_0/0.35)]">
            <svg class="row-span-2 size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 5 6 18"/><path d="M6 9v9h9"/></svg>
            <span class="col-start-2 font-serif text-[1.0625rem]/[1.2]">You are here</span>
            <span class="col-start-2 font-mono text-sm/[1.4] wrap-anywhere">{{ $requestedPath }}</span>
        </p>
    </x-slot:motif>
</x-error-page>
