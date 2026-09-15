export default function Checkbox({ className = "", ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={"rounded border-camel-600 text-camel-600 shadow-xs focus:ring-camel-500 " + className}
        />
    );
}
