import EmptyState from "@/Components/EmptyState";
import ItemRow from "@/Components/Loot/ItemRow";

const byName = (first, second) => first.name.localeCompare(second.name);

/**
 * Split items into named groups and ungrouped items, each sorted by name.
 */
function groupItems(items) {
    const groups = {};
    const ungroupedItems = [];

    items.forEach((item) => {
        if (item.group) {
            (groups[item.group] ??= []).push(item);
        } else {
            ungroupedItems.push(item);
        }
    });

    Object.values(groups).forEach((group) => group.sort(byName));
    ungroupedItems.sort(byName);

    return { groups, ungroupedItems };
}

/**
 * The pulsing placeholder shown while a deferred item list loads.
 */
export function ItemListSkeleton() {
    return (
        <div className="flex animate-pulse flex-col gap-2" aria-hidden="true">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="h-12 rounded bg-brown-800/50" />
            ))}
        </div>
    );
}

/**
 * Loot items as rows: each named group under its heading, then the ungrouped items.
 */
export default function ItemList({ items, weightThreshold, emptyMessage }) {
    if (items.length === 0) {
        return <EmptyState icon="sack" message={emptyMessage} />;
    }

    const { groups, ungroupedItems } = groupItems(items);

    return (
        <div className="flex flex-col gap-8">
            {Object.entries(groups).map(([groupName, groupItemsList]) => (
                <div key={groupName} className="flex flex-col gap-2">
                    <h3 className="text-sm font-semibold text-amber-500">{groupName}</h3>
                    {groupItemsList.map((item) => (
                        <ItemRow key={item.id} item={item} weightThreshold={weightThreshold} />
                    ))}
                </div>
            ))}
            {ungroupedItems.length > 0 && (
                <div className="flex flex-col gap-2">
                    {ungroupedItems.map((item) => (
                        <ItemRow key={item.id} item={item} weightThreshold={weightThreshold} />
                    ))}
                </div>
            )}
        </div>
    );
}
