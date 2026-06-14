export default function Checkbox({ className = "", ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={"rounded border-amber-600 text-amber-600 shadow-xs focus:ring-amber-500 " + className}
        />
    );
}
