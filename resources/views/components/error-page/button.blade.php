@props([
    'href',
    'variant' => 'primary',
])
<a {{ $attributes->class([
    'inline-flex items-center gap-2 rounded-md border px-5.5 py-3 font-semibold motion-safe:transition-colors focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-camelot-sunset [&_svg]:size-4',
    'border-transparent bg-camelot-sunset text-camelot-dusk hover:bg-camelot-sunset-light' => $variant === 'primary',
    'border-camelot-snow/30 text-camelot-snow hover:border-camelot-snow/60 hover:bg-camelot-snow/6' => $variant === 'secondary',
])->merge(['href' => $href]) }}>{{ $slot }}</a>
