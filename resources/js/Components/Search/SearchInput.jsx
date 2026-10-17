import Icon from "@/Components/FontAwesome/Icon";
import { TextInput } from "@/Components/FormControls";

export default function SearchInput({ value, onChange, placeholder = "Search by name...", dusk, inputRef }) {
    return (
        <div className="relative">
            <Icon icon="search" style="solid" className="absolute top-1/2 left-3 -translate-y-1/2 text-secondary-500" />
            <TextInput
                ref={inputRef}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                dusk={dusk}
                className="pr-10 pl-10"
            />
            {value && (
                <button
                    type="button"
                    onClick={() => onChange("")}
                    aria-label="Clear search"
                    className="absolute top-1/2 right-3 -translate-y-1/2 rounded text-secondary-500 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink-400"
                >
                    <Icon icon="times" style="solid" />
                </button>
            )}
        </div>
    );
}
