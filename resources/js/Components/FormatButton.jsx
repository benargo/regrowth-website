export default function FormatButton({ title, onClick, label = null }) {
    return (
        <div key={title} className="group relative">
            <button
                type="button"
                aria-label={label ? title : undefined}
                onClick={onClick}
                className={`bg-brown-700 hover:bg-brown-700 focus:ring-primary flex h-8 w-8 items-center justify-center rounded border border-amber-500 px-2 py-1 text-sm text-white focus:ring-1 focus:outline-hidden`}
            >
                {label || title}
            </button>
            <div className="pointer-events-none absolute top-full left-1/2 z-10 mt-1.5 -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100">
                <div className="absolute -top-1 left-1/2 -translate-x-1/2 border-4 border-transparent border-b-gray-900"></div>
                {title}
            </div>
        </div>
    );
}
