import { useState } from "react";
import Modal from "@/Components/Modal";
import TextInput from "@/Components/TextInput";
import Icon from "@/Components/FontAwesome/Icon";

export default function LevelRangeFilter({ minLevel, maxLevel, onMinChange, onMaxChange, dataMin, dataMax }) {
    const [isOpen, setIsOpen] = useState(false);
    const [draftMin, setDraftMin] = useState(minLevel ?? "");
    const [draftMax, setDraftMax] = useState(maxLevel ?? "");

    const openModal = () => {
        setDraftMin(minLevel ?? "");
        setDraftMax(maxLevel ?? "");
        setIsOpen(true);
    };

    const handleApply = () => {
        const parsedMin = draftMin !== "" ? parseInt(draftMin, 10) : null;
        const parsedMax = draftMax !== "" ? parseInt(draftMax, 10) : null;
        onMinChange(isNaN(parsedMin) ? null : parsedMin);
        onMaxChange(isNaN(parsedMax) ? null : parsedMax);
        setIsOpen(false);
    };

    const handleClear = () => {
        setDraftMin("");
        setDraftMax("");
        onMinChange(null);
        onMaxChange(null);
        setIsOpen(false);
    };

    let buttonLabel = "All Levels";
    if (minLevel !== null && maxLevel !== null) {
        buttonLabel = `Level ${minLevel}–${maxLevel}`;
    } else if (minLevel !== null) {
        buttonLabel = `Level ≥ ${minLevel}`;
    } else if (maxLevel !== null) {
        buttonLabel = `Level ≤ ${maxLevel}`;
    }

    return (
        <>
            <button
                onClick={openModal}
                className="flex w-full items-center justify-between rounded border border-amber-600 bg-brown-800 px-4 py-2 text-left text-white transition-colors hover:bg-brown-700"
            >
                <span className="truncate text-sm">{buttonLabel}</span>
                <Icon icon="sliders" className="ml-2 shrink-0 text-amber-500" />
            </button>

            <Modal show={isOpen} onClose={() => setIsOpen(false)} maxWidth="sm">
                <div className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-white">Filter by Level</h3>
                    <div className="flex items-center gap-3">
                        <TextInput
                            type="number"
                            value={draftMin}
                            onChange={(e) => setDraftMin(e.target.value)}
                            placeholder={String(dataMin)}
                            min={dataMin}
                            max={dataMax}
                            className="w-full"
                        />
                        <span className="shrink-0 text-gray-400">–</span>
                        <TextInput
                            type="number"
                            value={draftMax}
                            onChange={(e) => setDraftMax(e.target.value)}
                            placeholder={String(dataMax)}
                            min={dataMin}
                            max={dataMax}
                            className="w-full"
                        />
                    </div>
                    <p className="mt-2 text-xs text-gray-400">
                        Roster levels range from {dataMin} to {dataMax}
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            onClick={handleClear}
                            className="rounded border border-brown-700 px-4 py-2 text-sm text-gray-300 transition-colors hover:bg-brown-700 hover:text-white"
                        >
                            Clear
                        </button>
                        <button
                            onClick={handleApply}
                            className="rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-500"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
