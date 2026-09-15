export default function PrimaryButton({ className = '', disabled, processing = false, children, ...props }) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center gap-2 rounded-md border border-transparent bg-camel-600 px-4 py-2 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-camel-700 focus:outline-hidden focus:ring-2 focus:ring-camel-500 focus:ring-offset-2 ${
                    (disabled || processing) ? 'opacity-25' : ''
                } ` + className
            }
            disabled={disabled || processing}
        >
            {children}
        </button>
    );
}
