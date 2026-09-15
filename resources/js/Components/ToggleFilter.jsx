export default function ToggleFilter({ label, value, onChange }) {
    return (
        <label className="flex cursor-pointer items-center gap-3 rounded border border-camel-600 bg-forever-800 px-4 py-2">
            <span className="text-sm text-white">{label}</span>
            <div className="relative ml-auto">
                <input
                    type="checkbox"
                    checked={value}
                    onChange={(e) => onChange(e.target.checked)}
                    className="peer sr-only"
                />
                <div className="h-6 w-10 rounded-full bg-forever-700 transition-colors peer-checked:bg-camel-600" />
                <div className="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4" />
            </div>
        </label>
    );
}
