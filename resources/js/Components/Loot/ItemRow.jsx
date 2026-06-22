import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import InlinePriorityDisplay from "@/Components/Loot/InlinePriorityDisplay";

export default function ItemRow({ item, weightThreshold }) {
    const href = route("loot.items.show", { item: item.id, name: item.slug });
    const labelComments = item.comments_count === 1 ? "comment" : "comments";

    return (
        <div className="relative">
            <Link
                href={href}
                className="bg-brown-800/50 hover:bg-brown-800/70 flex items-center gap-4 rounded p-2 transition-colors"
            >
                {item.icon && <div className="h-8 w-8 flex-none" />}
                <div className="flex min-w-0 flex-none flex-col text-left">
                    <h4 className="text-md truncate font-bold">{item.name}</h4>
                    <div className="flex items-center gap-2">
                        {item.comments_count > 0 && (
                            <p className="inline-flex items-center gap-1 text-xs">
                                <Icon icon="comments" style="solid" className="h-3 w-3" />
                                {item.comments_count + " " + labelComments}
                            </p>
                        )}
                        {"notes" in item && (
                            <p className="inline-flex items-center gap-1 text-xs">
                                <Icon icon="sticky-note" style="solid" className="h-3 w-3" />
                                Notes
                            </p>
                        )}
                    </div>
                </div>
                <div className="ml-auto flex-1">
                    <InlinePriorityDisplay itemId={item.id} priorities={item.priorities} weightThreshold={weightThreshold} />
                </div>
            </Link>
            {item.icon && (
                <a
                    href={`https://www.wowhead.com/tbc/item=${item.id}`}
                    data-wowhead={`item=${item.id}&domain=tbc`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="absolute top-1/2 left-2 -translate-y-1/2"
                >
                    <img src={item.icon} alt={item.name} className="h-8 w-8 rounded" />
                </a>
            )}
        </div>
    );
}
