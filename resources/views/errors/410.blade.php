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
    <x-slot:heading>Jaina found this door boarded up</x-slot:heading>

    <x-slot:lead>
        This page existed once, but it&rsquo;s been removed for good. Old raid plans are cleared out a month after the raid. Head home to find what&rsquo;s current for raids, loot and the roster.
    </x-slot:lead>

    <x-slot:motif>
        {{-- One of the sticker's planks, nailed over wherever the visitor landed --}}
        <p class="mt-7 inline-grid max-w-full -rotate-[1.5deg] rounded-[4px_2px_5px_3px/3px_5px_2px_4px] border border-[#4a2f1a] bg-[#8a5a33] bg-[radial-gradient(circle_at_1rem_50%,#241a12_0.2rem,transparent_0.25rem),radial-gradient(circle_at_calc(100%_-_1rem)_50%,#241a12_0.2rem,transparent_0.25rem),repeating-linear-gradient(178deg,transparent_0_0.5rem,rgb(74_47_26/0.22)_0.5rem_0.5625rem,transparent_0.5625rem_0.875rem),linear-gradient(to_bottom,#a8743f,#8a5a33_55%,#4a2f1a)] px-9 py-3 text-left text-[#f4ead8] shadow-[inset_0_1px_0_rgb(255_255_255/0.12),0_10px_24px_rgb(0_0_0/0.35)] text-shadow-[0_1px_1px_rgb(36_26_18/0.6)]">
            <span class="font-serif text-[1.0625rem]/[1.2]">Closed for good</span>
            <span class="font-mono text-sm/[1.4] wrap-anywhere line-through decoration-2">{{ $requestedPath }}</span>
        </p>
    </x-slot:motif>
</x-error-page>
