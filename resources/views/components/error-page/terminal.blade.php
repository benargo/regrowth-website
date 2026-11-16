{{--
    The sticker's monitor, carried down into the page. Each direct child is one line.
--}}
<p {{ $attributes->class('mt-7 inline-flex max-w-full flex-col rounded-lg border border-terminal-glow/45 bg-terminal-screen px-4.5 py-3.5 text-left font-mono text-[0.9375rem]/[1.55] shadow-[inset_0_0_18px_rgb(134_227_212/0.12),0_0_24px_rgb(134_227_212/0.12)] *:flex *:flex-wrap *:items-baseline *:gap-x-2.5 *:wrap-anywhere') }}>
    {{ $slot }}
</p>
