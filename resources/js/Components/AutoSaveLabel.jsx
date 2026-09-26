import { useState, useEffect, useRef } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import { useAutosaveStatus } from "@/Hooks/useAutosave";

/**
 * "Saving…" while a save runs, then "Saved" briefly. Pass `processing` (or its
 * alias `saving`), or pass neither inside an AutosaveProvider to follow the
 * page's autosave queue, which also shows `errorMessage` when a save fails.
 */
export default function AutoSaveLabel({
    processing: processingProp,
    saving,
    savedDuration = 2000,
    errorMessage = "Couldn't save",
}) {
    const autosave = useAutosaveStatus();
    const fromProvider = processingProp === undefined && saving === undefined && autosave !== null;
    const processing = fromProvider ? autosave.status === "saving" : Boolean(processingProp ?? saving);
    const failed = fromProvider && autosave.status === "error";
    const [showSaved, setShowSaved] = useState(false);
    const prevProcessing = useRef(processing);
    const timer = useRef(null);

    useEffect(() => {
        if (prevProcessing.current && !processing) {
            clearTimeout(timer.current);
            setShowSaved(true);
            timer.current = setTimeout(() => setShowSaved(false), savedDuration);
        }
        prevProcessing.current = processing;

        return () => clearTimeout(timer.current);
    }, [processing]);

    if (processing) {
        return (
            <div className="text-ink-400 inline-flex items-center gap-2 text-sm">
                <Icon icon="spinner" style="solid" className="fa-spin" />
                <p>Saving...</p>
            </div>
        );
    }

    if (failed) {
        return (
            <div className="inline-flex items-center gap-2 text-sm text-red-300">
                <Icon icon="exclamation-triangle" style="solid" />
                <p>{errorMessage}</p>
            </div>
        );
    }

    if (showSaved) {
        return (
            <div className="inline-flex items-center gap-2 text-sm text-green-400">
                <Icon icon="check" style="solid" />
                <p>Saved</p>
            </div>
        );
    }

    return null;
}

export function AutoSaving() {
    return (
        <div className="text-ink-400 inline-flex items-center gap-2 text-sm">
            <Icon icon="spinner" style="solid" className="fa-spin" />
            <p>Saving...</p>
        </div>
    );
}

export function AutoSaved() {
    return (
        <div className="inline-flex items-center gap-2 text-sm text-green-400">
            <Icon icon="check" style="solid" />
            <p>Saved</p>
        </div>
    );
}
