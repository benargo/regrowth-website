import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import RaidCard from "@/Components/Loot/RaidCard";
import Icon from "@/Components/FontAwesome/Icon";
import { Can } from "@/Components/Authorizable";

function StatSegment({ icon, label, value, index }) {
    return (
        <div
            className="animate-fade-in-up flex min-w-32 flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200 hover:bg-amber-600/5"
            style={{ animationDelay: `${index * 60}ms` }}
        >
            <Icon
                icon={icon}
                style="light"
                className="flex size-6 items-center justify-center text-xl text-amber-500/70"
            />
            <div className="flex flex-col">
                <p className="text-xs font-medium tracking-wide text-gray-400 uppercase">{label}</p>
                <p className="text-2xl font-bold text-amber-400 tabular-nums">{value ?? "–"}</p>
            </div>
        </div>
    );
}

function StatsRow({ stats }) {
    const items = [
        { icon: "sack", label: "Items", value: stats.items_count },
        { icon: "list-ol", label: "Priority rows", value: stats.priority_rows_count },
        { icon: "comments", label: "Comments", value: stats.comments_count },
        { icon: "users", label: "Commenters", value: stats.commenters_count },
        { icon: "thumbs-up", label: "Reactions", value: stats.reactions_count },
    ];

    return (
        <div className="bg-brown-900/40 flex flex-col divide-y divide-amber-600/20 rounded-lg border border-amber-600/30 sm:flex-row sm:flex-wrap sm:divide-x sm:divide-y-0">
            {items.map((item, index) => (
                <StatSegment key={item.label} index={index} {...item} />
            ))}
            <Link
                href={route("loot.comments")}
                className="animate-fade-in-up group flex flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200 hover:bg-amber-600/10"
                style={{ animationDelay: `${items.length * 60}ms` }}
            >
                <Icon
                    icon="external-link"
                    style="light"
                    className="flex size-6 items-center justify-center text-xl text-amber-400"
                />
                <span className="flex items-center gap-1.5 text-sm font-semibold text-white">
                    View all comments
                    <Icon
                        icon="chevron-right"
                        style="solid"
                        className="flex size-3 items-center justify-center text-xs text-amber-400 transition-transform duration-200 group-hover:translate-x-0.5"
                    />
                </span>
            </Link>
            <Can permission="view-priorities-page">
                <Link
                    href={route("loot.priorities")}
                    className="animate-fade-in-up group flex flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200 hover:bg-amber-600/10"
                    style={{ animationDelay: `${(items.length + 1) * 60}ms` }}
                >
                    <Icon
                        icon="external-link"
                        style="light"
                        className="flex size-6 items-center justify-center text-xl text-amber-400"
                    />
                    <span className="flex items-center gap-1.5 text-sm font-semibold text-white">
                        Priority stats
                        <Icon
                            icon="chevron-right"
                            style="solid"
                            className="flex size-3 items-center justify-center text-xs text-amber-400 transition-transform duration-200 group-hover:translate-x-0.5"
                        />
                    </span>
                </Link>
            </Can>
        </div>
    );
}

export default function Index({ raids, stats }) {
    return (
        <Master title="Loot biases">
            <SharedHeader backgroundClass="bg-ssctk" title="Loot biases" />
            <PageContainer>
                <StatsRow stats={stats} />
                <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {raids.data.map((raid) => (
                        <RaidCard key={raid.id} raid={raid} />
                    ))}
                </div>
            </PageContainer>
        </Master>
    );
}
