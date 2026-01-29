

export default function FormatButton({ title, onClick, label = null }) {
    return (
        <div key={title} className="relative group">
            <button
                type="button"
                onClick={onClick}
                className={`w-8 h-8 flex items-center justify-center px-2 py-1 text-sm rounded border border-brown-600 bg-brown-700 text-white hover:bg-brown-600 focus:outline-none focus:ring-1 focus:ring-primary`}
            >
                {label || title}
            </button>
            <div className="absolute left-1/2 -translate-x-1/2 top-full mt-1 px-2 py-1 text-xs text-white bg-gray-900 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-150 pointer-events-none whitespace-nowrap z-10">
                {title}
                <div className="absolute left-1/2 -translate-x-1/2 -top-1 w-2 h-2 bg-gray-900 rotate-45"></div>
            </div>
        </div>
    )
};