export default function PrimaryButton({ className = '', disabled, processing = false, children, ...props }) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center gap-2 rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 ${
                    (disabled || processing) ? 'opacity-25' : ''
                } ` + className
            }
            disabled={disabled || processing}
        >
            {children}
        </button>
    );
}
