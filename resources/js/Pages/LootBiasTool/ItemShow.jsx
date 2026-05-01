import Master from "@/Layouts/Master";
import { Deferred, Link } from "@inertiajs/react";
import CommentsSection from "@/Components/Loot/CommentsSection";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";
import Notes from "@/Components/Loot/Notes";
import usePermission from "@/Hooks/Permissions";
import ItemDetailsCard from "@/Components/Loot/ItemDetailsCard";

function PriorityItem({ priority }) {
    return (
        <div className="md:w-50 flex w-full items-center justify-center gap-2 rounded-md border border-primary bg-brown-800 p-6">
            {priority.media && <img src={priority.media} alt="" className="h-6 w-6 rounded-sm" />}
            <span>{priority.title}</span>
        </div>
    );
}

function PriorityDisplay({ priorities }) {
    if (!priorities || priorities.length === 0) {
        return <p className="italic text-gray-500">This item has no biases.</p>;
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
                        <div className="mx-auto my-4 text-center text-4xl font-bold text-amber-600 md:ml-12">
                            <Icon icon="chevron-down" style="solid" />
                        </div>
                    )}
                    <div className="flex items-center justify-center rounded-md border border-amber-700 md:border-transparent">
                        <div className="mx-2 w-6 flex-none text-4xl">{weightIndex + 1}</div>
                        <div className="md:w-92 flex w-full flex-col items-center justify-center rounded px-2 py-4 md:flex-row">
                            {grouped[weight].map((priority, index) => (
                                <div
                                    key={`priority-${priority.id}`}
                                    className="flex w-full flex-col items-center justify-center md:w-auto md:flex-row"
                                >
                                    {index > 0 && (
                                        <div
                                            key={`separator-${index}`}
                                            className="my-4 w-12 flex-none items-center text-center text-2xl font-bold text-amber-600"
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

export default function ItemShow({ item, comments }) {
    const canEditItem = usePermission("edit-items");
    const canCreateComment = usePermission("comment-on-loot-items");

    return (
        <Master title={item.data.name}>
            <SharedHeader backgroundClass="bg-ssctk" title="Loot Bias" />
            {/* Tool navigation */}
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto px-4">
                    <div className="flex min-h-12 flex-col items-center justify-between md:flex-row">
                        <div className="flex-initial space-x-4">
                            <Link
                                href={route("loot.raids.show", { raid: item.data.raid.id, name: item.data.raid.slug })}
                                className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                            >
                                <Icon icon="arrow-left" style="solid" className="mr-2" />
                                <span>Back to {item.data.raid.name} loot</span>
                            </Link>
                        </div>
                        <div className="flex items-center space-x-4">
                            {canEditItem && (
                                <Link
                                    href={route("loot.items.edit", { item: item.data.id, name: item.data.slug })}
                                    className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
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
                <ItemDetailsCard item={item.data} />

                <h2 className="mb-4 mt-8 text-xl font-bold">Loot Biases</h2>
                {/* Biases List */}
                {item.data.priorities.length > 0 ? (
                    <div className="mt-8 w-full">
                        <PriorityDisplay priorities={item.data.priorities} />
                        <p className="text-gray-400">
                            Beyond the above biases, this item will be distributed <strong>MS &gt; OS</strong>.
                        </p>
                    </div>
                ) : (
                    <p className="text-gray-300">
                        No biases have been set for this item. This item will be distributed <strong>MS &gt; OS</strong>
                        .
                    </p>
                )}

                {/* Notes Section */}
                <Notes notes={item.data.notes} itemId={item.data.id} canEdit={false} />

                {/* Comments Section */}
                <CommentsSection comments={comments} itemId={item.data.id} canCreate={canCreateComment} />
            </main>
        </Master>
    );
}
