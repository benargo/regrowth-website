import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import ItemIcon from "@/Components/Items/ItemIcon";

export default function ItemResultRow({ item, index, isHighlighted, onMouseEnter }) {
    const href = route("loot.items.show", { item: item.id, slug: item.slug });
    const raidLabel = item.raids?.length > 1 ? "Multiple raids" : item.raids?.[0]?.name;
    const breadcrumb = [raidLabel, item.boss?.name].filter(Boolean).join(" › ");
    const labelComments = item.comments_count === 1 ? "comment" : "comments";

    return (
        <div className="relative">
            <Link
                href={href}
                id={`search-palette-option-${index}`}
                role="option"
                aria-selected={isHighlighted}
                tabIndex={-1}
                onMouseEnter={onMouseEnter}
                className={`flex items-center gap-4 rounded p-2 transition-colors ${
                    isHighlighted ? "bg-brown-700" : "bg-brown-800/50 hover:bg-brown-800/70"
                }`}
            >
                {item.icon && <div className="h-8 w-8 flex-none" />}
                <div className="flex min-w-0 flex-col text-left">
                    {breadcrumb && <p className="truncate text-xs text-gray-400">{breadcrumb}</p>}
                    <h4 className="text-md truncate font-bold text-white">{item.name}</h4>
                    <div className="flex items-center gap-2">
                        {item.comments_count > 0 && (
                            <p className="inline-flex items-center gap-1 text-xs text-gray-200">
                                <Icon icon="comments" style="solid" className="h-3 w-3" />
                                {`${item.comments_count} ${labelComments}`}
                            </p>
                        )}
                        {item.notes && (
                            <p className="inline-flex items-center gap-1 text-xs text-gray-200">
                                <Icon icon="sticky-note" style="solid" className="h-3 w-3" />
                                Notes
                            </p>
                        )}
                    </div>
                </div>
            </Link>
            {item.icon && (
                <div className="absolute top-1/2 left-2 -translate-y-1/2">
                    <ItemIcon
                        itemId={item.id}
                        itemName={item.name}
                        iconUrl={item.icon}
                        itemQuality={item.quality_border_class}
                        size={8}
                        wowheadUrl={item.wowhead?.url}
                    />
                </div>
            )}
        </div>
    );
}
