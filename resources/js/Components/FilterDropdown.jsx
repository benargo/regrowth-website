import { useEffect, useRef, useState } from "react";
import Icon from "@/Components/FontAwesome/Icon";

export default function FilterDropdown({ label, options, selected, onChange, showIcon = false }) {
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
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between rounded border border-line bg-surface px-4 py-2 text-left text-white transition-colors hover:bg-surface-raised"
            >
                <span className="truncate text-sm">{buttonText}</span>
                <Icon
                    icon="chevron-down"
                    className={`ml-2 shrink-0 text-body-muted transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded border border-line bg-surface shadow-lg">
                    <div className="flex border-b border-line">
                        <button
                            onClick={selectAll}
                            className="flex-1 px-3 py-2 text-sm text-body-muted transition-colors hover:bg-surface-raised"
                        >
                            All
                        </button>
                        <button
                            onClick={selectNone}
                            className="flex-1 border-l border-line px-3 py-2 text-sm text-body-muted transition-colors hover:bg-surface-raised"
                        >
                            None
                        </button>
                    </div>
                    <div className="py-1">
                        {options.map((option) => (
                            <label
                                key={option.id}
                                className="flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors hover:bg-surface-raised"
                            >
                                <input
                                    type="checkbox"
                                    checked={selected.includes(option.id)}
                                    onChange={() => toggleOption(option.id)}
                                    className="h-4 w-4 rounded border-line bg-surface-sunken text-line focus:ring-focus-ring focus:ring-offset-line"
                                />
                                {showIcon && option.media?.assets?.[0]?.value && (
                                    <img src={option.media.assets[0].value} alt="" className="h-5 w-5 rounded" />
                                )}
                                <span className="text-sm text-white">{option.name}</span>
                            </label>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
