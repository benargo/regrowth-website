export default function ToggleFilter({ label, value, onChange }) {
    return (
        <label className="bg-brown-800 flex cursor-pointer items-center gap-3 rounded border border-amber-600 px-4 py-2">
            <span className="text-sm text-white">{label}</span>
            <div className="relative ml-auto">
                <input
                    type="checkbox"
                    role="switch"
                    checked={value}
                    onChange={(e) => onChange(e.target.checked)}
                    className="peer sr-only"
                />
                <div
                    aria-hidden="true"
                    className="bg-brown-700 h-6 w-10 rounded-full transition-colors peer-checked:bg-amber-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-amber-400"
                />
                <div
                    aria-hidden="true"
                    className="absolute top-1 left-1 h-4 w-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"
                />
            </div>
        </label>
    );
}
