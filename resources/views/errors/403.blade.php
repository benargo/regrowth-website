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
    <x-slot:heading>Jaina can&rsquo;t let you in</x-slot:heading>

    <x-slot:lead>
        Your account doesn&rsquo;t have permission to open this page. Some pages are only for certain ranks or officers. If you think you should have access, ask an officer on Discord.
    </x-slot:lead>

    <x-slot:motif>
        {{-- A slab of the sticker's cave rock, carved with wherever the visitor tried to go --}}
        <p class="mt-7 inline-grid max-w-full -rotate-1 grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 rounded-[14px_6px_11px_4px/5px_12px_6px_10px] border border-[#3b3532] bg-[#6a615b] bg-linear-160 from-[#857b74] via-[#6a615b] via-45% to-[#3b3532] py-3.5 pr-5 pl-4 text-left text-[#2a2522] shadow-[inset_0_1px_0_rgb(255_255_255/0.14),inset_0_-3px_0_rgb(0_0_0/0.18),0_10px_24px_rgb(0_0_0/0.35)] text-shadow-[0_1px_0_rgb(255_255_255/0.18)]">
            <svg class="row-span-2 size-7 drop-shadow-[0_1px_0_rgb(255_255_255/0.18)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="1.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/></svg>
            <span class="col-start-2 font-serif text-[1.0625rem]/[1.2]">No entry</span>
            <span class="col-start-2 font-mono text-sm/[1.4] wrap-anywhere">{{ $requestedPath }}</span>
        </p>
    </x-slot:motif>
</x-error-page>
