import Icon from "@/Components/FontAwesome/Icon";
import IconButton from "@/Components/IconButton";
import { TextInput } from "@/Components/FormControls";

export default function SearchInput({ value, onChange, placeholder = "Search by name…", dusk, inputRef }) {
    return (
        <div className="relative h-full">
            <Icon icon="search" style="solid" className="absolute top-1/2 left-3 -translate-y-1/2 text-gray-400" />
            <TextInput
                ref={inputRef}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                dusk={dusk}
                className="h-full pr-10 pl-10"
            />
            {value && (
                <IconButton
                    icon="times"
                    label="Clear search"
                    onClick={() => onChange("")}
                    className="absolute top-1/2 right-3 -translate-y-1/2 rounded text-gray-400 hover:text-white"
                />
            )}
        </div>
    );
}
