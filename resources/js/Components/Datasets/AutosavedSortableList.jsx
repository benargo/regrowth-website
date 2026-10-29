import SortableList from "@/Components/Datasets/SortableList";
import useOrderAutosave from "@/Hooks/useOrderAutosave";

/**
 * A list of records officers drag into order. Dropping one saves the new
 * order straight away, as the records' ids in `field` alongside `data`, in
 * the save queue as `saveKey`; a status line announces the save.
 * renderItem(record, { index, dragHandle }) draws each record.
 */
export default function AutosavedSortableList({
    items,
    url,
    field,
    data = {},
    saveKey,
    label,
    renderItem,
    className = "",
}) {
    const { ordered, move, isSaving, hasSaved, error } = useOrderAutosave({
        items,
        getKey: (item) => item.id,
        url,
        field,
        data,
        key: saveKey,
    });

    return (
        <div className="flex flex-col gap-3">
            <SortableList
                items={ordered}
                getKey={(item) => item.id}
                getLabel={(item) => item.name}
                label={label}
                className={className}
                onMove={move}
                renderItem={renderItem}
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
