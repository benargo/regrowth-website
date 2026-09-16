export default function Checkbox({ className = "", ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={"rounded border-ink-600 text-ink-600 shadow-xs focus:ring-ink-500 " + className}
        />
    );
}
