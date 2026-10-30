import SortableList from "@/Components/Datasets/SortableList";
import useOrderAutosave from "@/Hooks/useOrderAutosave";

/**
 * A list of records officers drag into order. Dropping one saves the new
 * order straight away, as the records' keys in `field` alongside `data`, in
 * the save queue as `saveKey`, with the visit `method` (PATCH by default); a
 * status line announces the save. getKey(record) and getLabel(record) give
 * each record's key and name (its `id` and `name` by default).
 * renderItem(record, { index, dragHandle }) draws each record.
 */
export default function AutosavedSortableList({
    items,
    url,
    method = "patch",
    field,
    data = {},
    saveKey,
    getKey = (item) => item.id,
    getLabel = (item) => item.name,
    label,
    renderItem,
    className = "",
}) {
    const { ordered, move, isSaving, hasSaved, error } = useOrderAutosave({
        items,
        getKey,
        url,
        method,
        field,
        data,
        key: saveKey,
    });

    return (
        <div className="flex flex-col gap-3">
            <SortableList
                items={ordered}
                getKey={getKey}
                getLabel={getLabel}
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
