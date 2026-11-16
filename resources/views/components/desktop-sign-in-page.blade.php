@props([
    'title',
    'failed' => false,
    'discordLabel' => null,
])
{{--
    The Regrowth Desktop sign-in landing page: the branded error-page shell with
    Jaina's monitor carried down into the copy as a finished terminal session.
--}}
<x-error-page
    :title="$title"
    sticker="images/sticker_jaina_maintenance_503.webp"
    sticker-alt="Jaina the Samoyed in a headset, typing at a computer"
    sticker-width="1709"
    sticker-height="1611"
    :discord-label="$discordLabel"
    :home-action="false"
>
    <x-slot:heading>{{ $heading }}</x-slot:heading>

    <x-slot:lead>{{ $lead }}</x-slot:lead>

    <x-slot:motif>
        <x-error-page.terminal>
            <span>
                <x-error-page.terminal.prompt />
                <span aria-hidden="true" translate="no">regrowth-desktop sign-in</span>
            </span>
            <span @class(['text-terminal-glow' => ! $failed, 'text-camelot-sunset' => $failed])>{{ $result }}</span>
            <span aria-hidden="true">
                <x-error-page.terminal.prompt />
                <x-error-page.terminal.cursor />
            </span>
        </x-error-page.terminal>
    </x-slot:motif>
</x-error-page>
