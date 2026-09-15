export default function PrimaryButton({ className = '', disabled, processing = false, children, ...props }) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center gap-2 rounded-md border border-transparent bg-accent px-4 py-2 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-accent-hover focus:outline-hidden focus:ring-2 focus:ring-focus-ring focus:ring-offset-2 ${
                    (disabled || processing) ? 'opacity-25' : ''
                } ` + className
            }
            disabled={disabled || processing}
        >
            {children}
        </button>
    );
}
