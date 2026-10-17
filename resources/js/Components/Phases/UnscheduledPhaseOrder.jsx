import SortableList from "@/Components/Datasets/SortableList";
import useOrderAutosave from "@/Hooks/useOrderAutosave";

/**
 * The unscheduled phases of one game version, in the order officers expect
 * them to arrive. Dragging one saves the new order straight away; a status
 * line announces the save. renderPhase(phase, dragHandle) draws each phase.
 */
export default function UnscheduledPhaseOrder({ phases, url, field, data = {}, label, renderPhase, className = "" }) {
    const { ordered, move, isSaving, hasSaved, error } = useOrderAutosave({
        items: phases,
        getKey: (phase) => phase.id,
        url,
        field,
        data,
        key: `phase-order-${data.game_version_id ?? "none"}`,
    });

    return (
        <div className="flex flex-col gap-3">
            <SortableList
                items={ordered}
                getKey={(phase) => phase.id}
                getLabel={(phase) => phase.name}
                label={label}
                className={className}
                onMove={move}
                renderItem={(phase, { dragHandle }) => renderPhase(phase, dragHandle)}
            />
            <p role="status" aria-live="polite" className="min-h-5 text-sm text-gray-300">
                {isSaving ? "Saving the order…" : hasSaved ? "Order saved." : ""}
            </p>
            {error && (
                <p role="alert" className="text-sm text-red-400">
                    {error}
                </p>
            )}
        </div>
    );
}
