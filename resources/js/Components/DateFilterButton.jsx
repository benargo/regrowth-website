import { useState } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import TextInput from "@/Components/TextInput";

function formatDateValue(value, includeTime) {
    if (!value) return null;
    if (includeTime) {
        // value is "YYYY-MM-DDTHH:MM"
        const [datePart, timePart] = value.split("T");
        if (!datePart) return value;
        const formatted = datePart.split("-").reverse().join("/");
        return timePart ? `${formatted} ${timePart}` : formatted;
    }
    // value is "YYYY-MM-DD"
    return value.split("-").reverse().join("/");
}

export default function DateFilterButton({ label, value, onChange, onClear, min, max, includeTime = false, helpText = "Leave blank to show all available dates." }) {
    const [isOpen, setIsOpen] = useState(false);
    const [draft, setDraft] = useState(value);

    const defaultMax = includeTime
        ? new Date().toISOString().slice(0, 16)
        : new Date().toISOString().split("T")[0];

    const open = () => {
        setDraft(value);
        setIsOpen(true);
    };

    const close = () => setIsOpen(false);

    const apply = () => {
        onChange(draft);
        close();
    };

    const clear = () => {
        if (onClear) {
            onClear();
        } else {
            onChange("");
        }
        close();
    };

    const formattedValue = formatDateValue(value, includeTime);

    return (
        <>
            <button
                type="button"
                onClick={open}
                className={`flex w-full items-center justify-between rounded border px-4 py-2 text-left text-sm transition-colors hover:bg-brown-700 ${value ? "border-amber-500 bg-brown-800 text-white" : "border-amber-600 bg-brown-800 text-gray-400"}`}
            >
                <span className="flex items-center gap-2 truncate">
                    <Icon icon="calendar" style="regular" className="shrink-0 text-amber-500" />
                    {formattedValue ? `${label}: ${formattedValue}` : label}
                </span>
                {value && (
                    <span className="ml-2 shrink-0 rounded-full bg-amber-600 px-1.5 py-0.5 text-xs text-white">
                        set
                    </span>
                )}
            </button>

            <Modal show={isOpen} onClose={close} maxWidth="sm">
                <div className="p-6">
                    <h2 className="mb-1 text-lg font-bold text-white">{label} date</h2>
                    {typeof helpText === "string" ? (
                        <p className="mb-4 text-sm text-gray-400">{helpText}</p>
                    ) : (
                        <div className="mb-4 text-sm text-gray-400">{helpText}</div>
                    )}
                    <TextInput
                        type={includeTime ? "datetime-local" : "date"}
                        value={draft}
                        min={min}
                        max={max ?? defaultMax}
                        onChange={(e) => setDraft(e.target.value)}
                        className="block w-full bg-brown-800/50 text-white [color-scheme:dark]"
                    />
                    <div className="mt-6 flex justify-between gap-3">
                        <button
                            type="button"
                            onClick={clear}
                            className="inline-flex items-center gap-2 rounded-md border border-gray-500 bg-gray-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-600"
                        >
                            <Icon icon="times" style="solid" />
                            Clear
                        </button>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={close}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-gray-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-brown-600"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={apply}
                                className="inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-amber-700"
                            >
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            </Modal>
        </>
    );
}
