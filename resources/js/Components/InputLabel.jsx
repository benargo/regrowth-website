export default function InputLabel({
    value,
    className = '',
    required = false,
    children,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                'block text-sm font-medium text-secondary-300 ' +
                className
            }
        >
            {value ? value : children}
            {required && <span className="ml-1 text-red-400">*</span>}
        </label>
    );
}
