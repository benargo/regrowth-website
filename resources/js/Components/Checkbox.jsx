export default function Checkbox({ className = "", ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={"rounded border-line text-line shadow-xs focus:ring-focus-ring " + className}
        />
    );
}
