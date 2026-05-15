import { useState, useEffect, useRef } from "react";
import Icon from "@/Components/FontAwesome/Icon";

export default function AutoSaveLabel({ processing, savedDuration = 2000 }) {
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
            <div className="inline-flex items-center gap-2 text-sm text-amber-400">
                <Icon icon="spinner" style="solid" className="fa-spin" />
                <p>Saving...</p>
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
        <div className="inline-flex items-center gap-2 text-sm text-amber-400">
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
