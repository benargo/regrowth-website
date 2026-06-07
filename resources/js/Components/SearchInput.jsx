import Icon from "@/Components/FontAwesome/Icon";

export default function SearchInput({ value, onChange, placeholder = "Search by name...", dusk }) {
    return (
        <div className="relative">
            <Icon icon="search" style="solid" className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                dusk={dusk}
                className="w-full rounded border border-amber-600 bg-brown-800 py-2 pl-10 pr-10 text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            {value && (
                <button
                    onClick={() => onChange("")}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                >
                    <Icon icon="times" style="solid" />
                </button>
            )}
        </div>
    );
}
