export default function SecondaryButton({ type = 'button', className = '', disabled, children, ...props }) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-md border border-secondary-300 bg-secondary-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-xs transition hover:bg-ground-700 focus:outline-hidden focus:ring-2 focus:ring-ink-500 focus:ring-offset-2 ${
                    disabled ? 'opacity-25' : ''
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
