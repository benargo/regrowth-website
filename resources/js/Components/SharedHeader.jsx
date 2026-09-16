export default function SharedHeader({ title, subtitle, backgroundClass = "bg-masthead" }) {
    return (
        <header className={`${backgroundClass} border-ink-600 h-64 border-b-8 text-white md:h-128`}>
            <div className="flex h-full w-full items-center bg-black/50 px-4 pt-20.5 pb-8 lg:pt-21.5">
                <div className="container mx-auto">
                    <h1 className="from-ink-100 to-ink-300 mb-3 bg-linear-to-b bg-clip-text pb-1 text-center font-serif text-5xl text-transparent drop-shadow-[0_2px_12px_rgba(0,0,0,0.8)] text-shadow-sm">
                        {title}
                        {subtitle && (
                            <span className="from-ink-300 to-ink-500 text-ink-400 mt-2 block bg-linear-to-b bg-clip-text text-xl">
                                {subtitle}
                            </span>
                        )}
                    </h1>
                </div>
            </div>
        </header>
    );
}
