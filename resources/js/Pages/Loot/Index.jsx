import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Card from "@/Components/Card";
import Icon from "@/Components/FontAwesome/Icon";
import { Can } from "@/Components/Authorizable";

function StatSegment({ icon, label, value, index }) {
    return (
        <div
            className="animate-fade-in-up hover:bg-ink-600/5 flex min-w-32 flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200"
            style={{ animationDelay: `${index * 60}ms` }}
        >
            <Icon
                icon={icon}
                style="light"
                className="text-ink-500/70 flex size-6 items-center justify-center text-xl"
            />
            <div className="flex flex-col">
                <p className="text-secondary-400 text-xs font-medium tracking-wide uppercase">{label}</p>
                <p className="text-ink-400 text-2xl font-bold tabular-nums">{value ?? "–"}</p>
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
        <div className="bg-ground-900/40 divide-ink-600/20 border-ink-600/30 flex flex-col divide-y rounded-lg border sm:flex-row sm:flex-wrap sm:divide-x sm:divide-y-0">
            {items.map((item, index) => (
                <StatSegment key={item.label} index={index} {...item} />
            ))}
            <Link
                href={route("loot.comments")}
                className="animate-fade-in-up group hover:bg-ink-600/10 flex flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200"
                style={{ animationDelay: `${items.length * 60}ms` }}
            >
                <Icon
                    icon="external-link"
                    style="light"
                    className="text-ink-400 flex size-6 items-center justify-center text-xl"
                />
                <span className="flex items-center gap-1.5 text-sm font-semibold text-white">
                    View all comments
                    <Icon
                        icon="chevron-right"
                        style="solid"
                        className="text-ink-400 flex size-3 items-center justify-center text-xs transition-transform duration-200 group-hover:translate-x-0.5"
                    />
                </span>
            </Link>
            <Can permission="view-priorities-page">
                <Link
                    href={route("loot.priorities")}
                    className="animate-fade-in-up group hover:bg-ink-600/10 flex flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200"
                    style={{ animationDelay: `${(items.length + 1) * 60}ms` }}
                >
                    <Icon
                        icon="external-link"
                        style="light"
                        className="text-ink-400 flex size-6 items-center justify-center text-xl"
                    />
                    <span className="flex items-center gap-1.5 text-sm font-semibold text-white">
                        Priority stats
                        <Icon
                            icon="chevron-right"
                            style="solid"
                            className="text-ink-400 flex size-3 items-center justify-center text-xs transition-transform duration-200 group-hover:translate-x-0.5"
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
            <SharedHeader backgroundClass="bg-vashj-and-kaelthas" title="Loot biases" />
            <PageContainer>
                <StatsRow stats={stats} />
                <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {raids.data.map((raid) => (
                        <Card
                            key={raid.id}
                            href={route("loot.raids.show", { raid: raid.id, name: raid.slug })}
                            backgroundClass={raid.background ?? "bg-ground-900"}
                            color={raid.color}
                            eyebrow={raid.phase_number ? `Phase ${raid.phase_number}` : null}
                            heading={raid.name}
                        />
                    ))}
                </div>
            </PageContainer>
        </Master>
    );
}
