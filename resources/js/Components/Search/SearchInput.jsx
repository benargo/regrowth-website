import Icon from "@/Components/FontAwesome/Icon";

export default function SearchInput({ value, onChange, placeholder = "Search by name...", dusk, inputRef }) {
    return (
        <div className="relative">
            <Icon icon="search" style="solid" className="absolute top-1/2 left-3 -translate-y-1/2 text-gray-500" />
            <input
                ref={inputRef}
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                dusk={dusk}
                className="border-brown-600 bg-brown-800 w-full rounded border py-2 pr-10 pl-10 text-white placeholder-gray-500 focus:ring-1 focus:ring-amber-500 focus:outline-hidden"
            />
            {value && (
                <button
                    onClick={() => onChange("")}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-gray-500 hover:text-white"
                >
                    <Icon icon="times" style="solid" />
                </button>
            )}
        </div>
    );
}
