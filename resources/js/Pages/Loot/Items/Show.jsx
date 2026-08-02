import { useState } from "react";
import Master from "@/Layouts/Master";
import { Link } from "@inertiajs/react";
import useItemChannel from "@/Hooks/useItemChannel";
import CommentsSection from "@/Components/Loot/CommentsSection";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import { Can } from "@/Components/Authorizable";
import ItemDetailsCard from "@/Components/Loot/ItemDetailsCard";
import ToolNav from "@/Components/ToolNav";
import PageContainer from "@/Components/PageContainer";

function PriorityItem({ priority }) {
    return (
        <div className="border-primary bg-brown-800 flex w-auto items-center justify-center gap-2 rounded-md border px-4 py-3">
            {priority.media && <img src={priority.media} alt="" className="h-6 w-6 rounded-xs" />}
            <span>{priority.title}</span>
        </div>
    );
}

function PriorityDisplay({ priorities }) {
    if (!priorities || priorities.length === 0) {
        return <p className="text-gray-500 italic">This item has no biases.</p>;
    }

    const sorted = [...priorities].sort((a, b) => a.weight - b.weight);
    const grouped = sorted.reduce((acc, priority) => {
        const weight = priority.weight;
        if (!acc[weight]) acc[weight] = [];
        acc[weight].push(priority);
        return acc;
    }, {});

    const weights = Object.keys(grouped).sort((a, b) => a - b);

    const rowBgs = ["bg-brown-900/60", "bg-brown-800/60", "bg-brown-700/60", "bg-brown-600/60"];

    return (
        <div className="overflow-hidden rounded-md text-lg">
            {weights.map((weight, weightIndex) => (
                <div key={weight}>
                    {weightIndex > 0 && (
                        <div className="flex justify-center py-1 text-2xl text-amber-700/60">
                            <Icon icon="chevron-down" style="solid" />
                        </div>
                    )}
                    <div
                        className={`flex items-center gap-3 px-3 py-4 ${rowBgs[Math.min(weightIndex, rowBgs.length - 1)]}`}
                    >
                        <div className="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-amber-600 text-lg font-bold text-amber-600">
                            {weightIndex + 1}
                        </div>
                        <div className="flex flex-1 flex-wrap items-center justify-center gap-2">
                            {grouped[weight].map((priority, index) => (
                                <div key={`priority-${priority.id}`} className="flex items-center">
                                    {index > 0 && (
                                        <div
                                            key={`separator-${index}`}
                                            className="mx-2 flex-none text-center text-2xl font-bold text-amber-600"
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
        </div>
    );
}

export default function Show({ item }) {
    const raid = item.data.raid;
    const [notes, setNotes] = useState(item.data.notes);
    const [priorities, setPriorities] = useState(item.data.priorities);

    useItemChannel(item.data.id, (payload) => {
        if (payload.notes !== undefined) {
            setNotes(payload.notes);
        }
        if (payload.priorities !== undefined) {
            setPriorities(payload.priorities);
        }
    });

    return (
        <Master title={item.data.name}>
            <SharedHeader
                backgroundClass={raid?.background ?? "bg-ssctk"}
                title="Loot Bias"
                subtitle={raid?.name}
            />
            {/* Tool navigation */}
            <ToolNav>
                {raid && (
                    <div className="flex-initial space-x-4">
                        <Link
                            href={route("loot.raids.show", { raid: raid.id, name: raid.slug })}
                            className="hover:border-primary hover:bg-brown-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white"
                        >
                            <Icon icon="arrow-left" style="solid" className="mr-2" />
                            <span>Back to {raid.name} loot</span>
                        </Link>
                    </div>
                )}
                <div className="flex items-center space-x-4">
                    <Can permission="edit-items">
                        <Link
                            href={route("loot.items.edit", { item: item.data.id, slug: item.data.slug })}
                            className="hover:border-primary hover:bg-brown-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white"
                        >
                            <Icon icon="edit" style="solid" className="mr-2" />
                            <span>Edit this item</span>
                        </Link>
                    </Can>
                </div>
            </ToolNav>
            {/* Content */}
            <PageContainer>
                <ItemDetailsCard item={item.data} />

                <div className="border-brown-700 my-8 border-t" />

                <h2 className="mb-4 text-xl font-bold">Loot Biases</h2>
                {/* Biases List */}
                {priorities.length > 0 ? (
                    <div className="w-full">
                        <PriorityDisplay priorities={priorities} />
                        <p className="mt-4 text-gray-400">
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
                {notes && (
                    <div className="mt-8">
                        <h2 className="mb-6 text-xl font-bold">Officers&rsquo; Notes</h2>
                        <FormattedMarkdown>{notes}</FormattedMarkdown>
                    </div>
                )}

                {/* Comments Section */}
                <CommentsSection comments={item.data.comments} itemId={item.data.id} />
            </PageContainer>
        </Master>
    );
}
