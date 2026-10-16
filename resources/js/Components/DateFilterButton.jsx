import { useState } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import { PrimaryButton, SecondaryButton, TextInput } from "@/Components/FormControls";

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

export default function DateFilterButton({ id, label, value, onChange, onClear, min, max, includeTime = false, helpText = "Leave blank to show all available dates." }) {
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
                id={id}
                type="button"
                onClick={open}
                className={`flex w-full items-center justify-between rounded border px-4 py-2 text-left text-sm transition-colors hover:bg-ground-700 ${value ? "border-ink-500 bg-ground-800 text-white" : "border-ink-600 bg-ground-800 text-secondary-400"}`}
            >
                <span className="flex items-center gap-2 truncate">
                    <Icon icon="calendar" style="regular" className="shrink-0 text-ink-500" />
                    {formattedValue ? `${label}: ${formattedValue}` : label}
                </span>
                {value && (
                    <span className="ml-2 shrink-0 rounded-full bg-ink-600 px-1.5 py-0.5 text-xs text-white">
                        set
                    </span>
                )}
            </button>

            <Modal show={isOpen} onClose={close} maxWidth="sm">
                <div className="p-6">
                    <h2 className="mb-1 text-lg font-bold text-white">{label} date</h2>
                    {typeof helpText === "string" ? (
                        <p className="mb-4 text-sm text-secondary-400">{helpText}</p>
                    ) : (
                        <div className="mb-4 text-sm text-secondary-400">{helpText}</div>
                    )}
                    <TextInput
                        type={includeTime ? "datetime-local" : "date"}
                        aria-label={`${label} date`}
                        value={draft}
                        min={min}
                        max={max ?? defaultMax}
                        onChange={(e) => setDraft(e.target.value)}
                        className="scheme-dark"
                    />
                    <div className="mt-6 flex justify-between gap-3">
                        <SecondaryButton onClick={clear}>
                            <Icon icon="times" style="solid" />
                            Clear
                        </SecondaryButton>
                        <div className="flex gap-3">
                            <SecondaryButton onClick={close}>Cancel</SecondaryButton>
                            <PrimaryButton onClick={apply}>Apply</PrimaryButton>
                        </div>
                    </div>
                </div>
            </Modal>
        </>
    );
}
