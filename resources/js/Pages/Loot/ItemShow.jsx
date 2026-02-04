import Master from "@/Layouts/Master";
import { Link } from "@inertiajs/react";
import CommentsSection from "@/Components/Loot/CommentsSection";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";
import Notes from "@/Components/Loot/Notes";

function PriorityItem({ priority }) {
    return (
        <div className="min-w-50 flex items-center justify-center gap-2 rounded-md border border-primary bg-brown-800 p-6">
            {priority.media && <img src={priority.media} alt="" className="h-6 w-6 rounded-sm" />}
            <span>{priority.title}</span>
        </div>
    );
}

function PriorityDisplay({ priorities }) {
    if (!priorities || priorities.length === 0) {
        return <p className="italic text-gray-500">Item not subject to loot council.</p>;
    }

    // Sort by weight (ascending) and group by weight
    const sorted = [...priorities].sort((a, b) => a.weight - b.weight);
    const grouped = sorted.reduce((acc, priority) => {
        const weight = priority.weight;
        if (!acc[weight]) {
            acc[weight] = [];
        }
        acc[weight].push(priority);
        return acc;
    }, {});

    // Build display: join same-weight with " = ", different weights with " > "
    const weights = Object.keys(grouped).sort((a, b) => a - b);

    return (
        <span className="flex-col flex-wrap items-center gap-2 text-lg">
            {weights.map((weight, weightIndex) => (
                <div key={weight} className="my-4">
                    {weightIndex > 0 && (
                        <div className="mx-1 my-4 ml-12 text-center text-4xl font-bold text-amber-600">
                            <Icon icon="chevron-down" style="solid" />
                        </div>
                    )}
                    <div className="flex items-center justify-center">
                        <div className="w-12 flex-none text-4xl">{weightIndex + 1}</div>
                        <div className="flex w-full items-center justify-center">
                            {grouped[weight].map((priority, index) => (
                                <div
                                    key={`priority-${priority.id}`}
                                    className="w-92 my-4 flex items-center justify-center"
                                >
                                    {index > 0 && (
                                        <div
                                            key={`separator-${index}`}
                                            className="w-12 flex-none items-center text-center text-2xl font-bold text-amber-600"
                                        >
                                            <Icon icon="equals" style="solid" />
                                        </div>
                                    )}
                                    <PriorityItem priority={priority} />
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            ))}
        </span>
    );
}

export default function ItemShow({ item, can, comments }) {
    return (
        <Master title={item.data.name}>
            <SharedHeader backgroundClass="bg-karazhan" title="Loot Bias" />
            {/* Tool navigation */}
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto px-4">
                    <div className="flex min-h-12 flex-col items-center justify-between md:flex-row">
                        <div className="flex-initial space-x-4">
                            <Link
                                href={route("loot.index", { raid_id: item.data.raid.id })}
                                className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                            >
                                <Icon icon="arrow-left" style="solid" className="mr-2" />
                                <span>Back to {item.data.raid.name} loot</span>
                            </Link>
                        </div>
                        <div className="flex items-center space-x-4">
                            {can.edit_item && (
                                <Link
                                    href={route("loot.items.edit", { item: item.data.id })}
                                    className="fflex my-2 flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                                >
                                    <Icon icon="edit" style="solid" className="mr-2" />
                                    <span>Edit this item</span>
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </nav>
            {/* Content */}
            <main className="container mx-auto px-4 py-8">
                <div className="flex flex-row items-start gap-2 md:gap-6">
                    <div className="h-8 w-8 flex-none md:h-24 md:w-24">
                        <Link
                            href={item.data.wowhead_url}
                            data-wowhead={`item=${item.data.id}&domain=tbc`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <img
                                src={item.data.icon}
                                alt={item.data.name}
                                className="box-shadow h-8 w-8 rounded-lg md:h-24 md:w-24"
                            />
                        </Link>
                    </div>
                    <div className="flex w-full flex-initial flex-col">
                        <h2
                            className={`text-2xl font-bold text-quality-${item.data.quality?.name?.toLowerCase() || "common"} mb-2`}
                        >
                            {item.data.name}
                        </h2>
                        <div className="mb-4 flex flex-col gap-2 md:flex-row">
                            {/* Item Details */}
                            <div className="flex-auto">
                                {item.data.id && (
                                    <p className="mb-2">
                                        <strong>Item ID:</strong> {item.data.id}
                                    </p>
                                )}
                                {item.data.item_class && (
                                    <p className="mb-2">
                                        <strong>Type:</strong> {item.data.item_class}
                                        {item.data.item_subclass ? ` / ${item.data.item_subclass}` : ""}
                                    </p>
                                )}
                                {item.data.inventory_type && (
                                    <p className="mb-2">
                                        <strong>Slot:</strong> {item.data.inventory_type}
                                    </p>
                                )}
                                {item.data.boss && (
                                    <p className="mb-2">
                                        <strong>Drops from:</strong> {item.data.boss.name}
                                    </p>
                                )}
                                {item.data.group && (
                                    <p className="mb-2">
                                        <strong>Group:</strong> {item.data.group}
                                    </p>
                                )}
                            </div>
                            {/* Wowhead Link */}
                            <div className="flex-auto md:text-right">
                                <Link
                                    href={item.data.wowhead_url}
                                    data-wowhead={`item=${item.data.id}&domain=tbc`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-block rounded-md bg-wowhead px-4 py-2 font-medium text-white transition-opacity hover:opacity-90"
                                >
                                    <img
                                        src="/images/logo_wowhead_white.webp"
                                        alt="Wowhead Logo"
                                        className="-mt-1 mr-2 inline-block h-5 w-5"
                                    />
                                    View on Wowhead
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                <h2 className="mb-4 mt-8 text-xl font-bold">Loot Priorities</h2>
                {/* Priorities List */}
                {item.data.priorities.length > 0 ? (
                    <div className="mt-8 w-full">
                        <PriorityDisplay priorities={item.data.priorities} />
                    </div>
                ) : (
                    <p className="text-gray-300">No loot priorities have been set for this item.</p>
                )}

                {/* Notes Section */}
                <Notes notes={item.data.notes} itemId={item.data.id} canEdit={false} />

                {/* Comments Section */}
                <CommentsSection comments={comments} itemId={item.data.id} canCreate={can?.create_comment} />
            </main>
        </Master>
    );
}
