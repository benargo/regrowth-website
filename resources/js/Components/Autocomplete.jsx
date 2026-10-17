import { useState, useRef, useEffect, useId } from "react";
import { TextInput } from "@/Components/FormControls";

export default function Autocomplete({
    id,
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
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const listboxId = `${inputId}-listbox`;
    const isListboxOpen = isOpen && filteredOptions.length > 0;
    const optionId = (option) => `${listboxId}-${option.id}`;

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
                setHighlightedIndex((prev) => (prev < filteredOptions.length - 1 ? prev + 1 : prev));
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
                break;
        }
    };

    return (
        <div ref={containerRef} className="relative">
            {(icon || labelText) && (
                <label htmlFor={inputId} className="mb-2 flex flex-row items-center">
                    {icon && <img src={icon} alt={iconAlt} className="mr-2 h-6 w-6 rounded-xs" />}
                    {labelText && <span className="text-lg font-semibold">{labelText}</span>}
                </label>
            )}

            <TextInput
                ref={inputRef}
                id={inputId}
                value={value}
                role="combobox"
                aria-expanded={isListboxOpen}
                aria-controls={isListboxOpen ? listboxId : undefined}
                aria-autocomplete="list"
                aria-activedescendant={
                    isListboxOpen && filteredOptions[highlightedIndex]
                        ? optionId(filteredOptions[highlightedIndex])
                        : undefined
                }
                onChange={handleInputChange}
                onFocus={() => setIsOpen(true)}
                onBlur={() => setIsOpen(false)}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                required={required}
                autoComplete="off"
            />

            {isListboxOpen && (
                <div
                    id={listboxId}
                    role="listbox"
                    onMouseDown={(event) => event.preventDefault()}
                    className="bg-brown-800 absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded border border-amber-600 shadow-xl"
                >
                    {filteredOptions.map((option, index) => (
                        <div
                            key={option.id}
                            id={optionId(option)}
                            role="option"
                            aria-selected={index === highlightedIndex}
                            onClick={() => handleOptionClick(option)}
                            onMouseEnter={() => setHighlightedIndex(index)}
                            className={`cursor-pointer px-4 py-2 transition-colors ${
                                index === highlightedIndex
                                    ? "bg-amber-700 text-white"
                                    : "text-gray-300 hover:bg-amber-600/20"
                            }`}
                        >
                            {renderOption(option)}
                        </div>
                    ))}
                </div>
            )}

            {isOpen && filteredOptions.length === 0 && value && (
                <div className="bg-brown-800 absolute z-10 mt-1 w-full rounded border border-amber-600 px-4 py-2 text-gray-300 shadow-xl">
                    No matches found
                </div>
            )}

            {error && <div className="mt-1 text-sm text-red-500">{error}</div>}
        </div>
    );
}
