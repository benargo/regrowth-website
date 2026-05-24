export default function SecondaryButton({ type = 'button', className = '', disabled, children, ...props }) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-md border border-gray-300 bg-gray-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-brown-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 ${
                    disabled ? 'opacity-25' : ''
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
