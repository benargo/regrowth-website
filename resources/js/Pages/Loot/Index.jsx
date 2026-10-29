import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Icon from "@/Components/FontAwesome/Icon";
import SectionNav from "@/Components/Navigation/SectionNav";
import SectionCards from "@/Components/Navigation/SectionCards";

function StatSegment({ icon, label, value, index }) {
    return (
        <div
            className="animate-fade-in-up hover:bg-amber-600/5 flex min-w-32 flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200"
            style={{ animationDelay: `${index * 60}ms` }}
        >
            <Icon
                icon={icon}
                style="light"
                className="text-amber-500/70 flex size-6 items-center justify-center text-xl"
            />
            <div className="flex flex-col">
                <p className="text-gray-400 text-xs font-medium tracking-wide uppercase">{label}</p>
                <p className="text-amber-400 text-2xl font-bold tabular-nums">{value ?? "–"}</p>
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
        <div className="bg-brown-900/40 divide-amber-600/20 border-amber-600/30 flex flex-col divide-y rounded-lg border sm:flex-row sm:flex-wrap sm:divide-x sm:divide-y-0">
            {items.map((item, index) => (
                <StatSegment key={item.label} index={index} {...item} />
            ))}
        </div>
    );
}

export default function Index({ stats }) {
    return (
        <Master title="Loot biases">
            <SharedHeader backgroundClass="bg-vashj-and-kaelthas" title="Loot biases" />
            <SectionNav section="loot" />
            <PageContainer>
                <SectionCards section="loot" />
                <StatsRow stats={stats} />
            </PageContainer>
        </Master>
    );
}
