import { useState, useRef, useEffect } from "react";

export default function Autocomplete({
    value,
    onChange,
    options,
    placeholder,
    icon,
    iconAlt,
    labelText,
    error,
    required = false,
    renderOption = (option) => option.name,
    getOptionValue = (option) => option.name,
    getSearchableText = (option) => [option.name, option.instance].filter(Boolean).join(" "),
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [filteredOptions, setFilteredOptions] = useState(options);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const containerRef = useRef(null);
    const inputRef = useRef(null);

    // Normalize string for comparison
    const normalizeString = (str) => {
        if (!str) return "";
        return str
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[\u2018\u2019]/g, "'")
            .toLowerCase()
            .trim();
    };

    // Filter options based on input
    useEffect(() => {
        if (!value) {
            setFilteredOptions(options);
            return;
        }

        const normalized = normalizeString(value);
        const filtered = options.filter((option) => {
            const searchable = normalizeString(getSearchableText(option));
            return searchable.includes(normalized);
        });
        setFilteredOptions(filtered);
        setHighlightedIndex(-1);
    }, [value, options]);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };

        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const handleInputChange = (e) => {
        onChange(e.target.value);
        setIsOpen(true);
    };

    const handleOptionClick = (option) => {
        onChange(getOptionValue(option));
        setIsOpen(false);
        inputRef.current?.blur();
    };

    const handleKeyDown = (e) => {
        if (!isOpen) {
            if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                setIsOpen(true);
                e.preventDefault();
            }
            return;
        }

        switch (e.key) {
            case "ArrowDown":
                e.preventDefault();
                setHighlightedIndex((prev) =>
                    prev < filteredOptions.length - 1 ? prev + 1 : prev
                );
                break;
            case "ArrowUp":
                e.preventDefault();
                setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : -1));
                break;
            case "Enter":
                e.preventDefault();
                if (highlightedIndex >= 0 && filteredOptions[highlightedIndex]) {
                    handleOptionClick(filteredOptions[highlightedIndex]);
                }
                break;
            case "Escape":
                setIsOpen(false);
                inputRef.current?.blur();
                break;
        }
    };

    return (
        <div ref={containerRef} className="relative">
            {(icon || labelText) && (
                <label className="mb-2 flex flex-row items-center">
                    {icon && (
                        <img src={icon} alt={iconAlt} className="mr-2 h-6 w-6 rounded-sm" />
                    )}
                    {labelText && <span className="text-lg font-semibold">{labelText}</span>}
                </label>
            )}

            <input
                ref={inputRef}
                type="text"
                value={value}
                onChange={handleInputChange}
                onFocus={() => setIsOpen(true)}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                required={required}
                className="w-full rounded border border-amber-600 bg-brown-800 px-4 py-2 text-white placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500"
                autoComplete="off"
            />

            {isOpen && filteredOptions.length > 0 && (
                <div className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded border border-amber-600 bg-brown-800 shadow-xl">
                    {filteredOptions.map((option, index) => (
                        <div
                            key={option.id}
                            onClick={() => handleOptionClick(option)}
                            onMouseEnter={() => setHighlightedIndex(index)}
                            className={`cursor-pointer px-4 py-2 transition-colors ${
                                index === highlightedIndex
                                    ? "bg-amber-600 text-white"
                                    : "text-gray-300 hover:bg-amber-600/20"
                            }`}
                        >
                            {renderOption(option)}
                        </div>
                    ))}
                </div>
            )}

            {isOpen && filteredOptions.length === 0 && value && (
                <div className="absolute z-10 mt-1 w-full rounded border border-amber-600 bg-brown-800 px-4 py-2 text-gray-400 shadow-xl">
                    No matches found
                </div>
            )}

            {error && <div className="mt-1 text-sm text-red-500">{error}</div>}
        </div>
    );
}
