import { useEffect, useRef, useState } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import { Checkbox } from "@/Components/FormControls";

export default function FilterDropdown({ label, options, selected, onChange }) {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const toggleOption = (id) => {
        onChange(selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);
    };

    const selectAll = () => onChange(options.map((o) => o.id));
    const selectNone = () => onChange([]);

    const count = selected.length;
    const total = options.length;
    let buttonText;
    if (count === 0) buttonText = `No ${label.plural}`;
    else if (count === total) buttonText = `All ${label.plural}`;
    else if (count === 1) buttonText = `1 ${label.singular}`;
    else buttonText = `${count} ${label.plural}`;

    return (
        <div className="relative h-full" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex h-full min-h-10 w-full items-center justify-between rounded border border-amber-600 bg-brown-800 px-4 py-2 text-left text-white transition-colors hover:bg-brown-700"
            >
                <span className="truncate text-sm">{buttonText}</span>
                <Icon
                    icon="chevron-down"
                    className={`ml-2 shrink-0 text-amber-500 transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded border border-amber-600 bg-brown-800 shadow-lg">
                    <div className="flex border-b border-amber-600">
                        <button
                            onClick={selectAll}
                            className="flex-1 px-3 py-2 text-sm text-amber-500 transition-colors hover:bg-brown-700 hover:text-amber-400"
                        >
                            All
                        </button>
                        <button
                            onClick={selectNone}
                            className="flex-1 border-l border-amber-600 px-3 py-2 text-sm text-amber-500 transition-colors hover:bg-brown-700 hover:text-amber-400"
                        >
                            None
                        </button>
                    </div>
                    <div className="py-1">
                        {options.map((option) => (
                            <label
                                key={option.id}
                                className="flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors hover:bg-brown-700"
                            >
                                <Checkbox checked={selected.includes(option.id)} onChange={() => toggleOption(option.id)} />
                                <span className="text-sm text-white">{option.name}</span>
                            </label>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
