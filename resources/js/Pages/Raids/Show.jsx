import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Icon from "@/Components/FontAwesome/Icon";
import EmptyState from "@/Components/EmptyState";
import Breadcrumbs from "@/Components/Navigation/Breadcrumbs";
import SectionNav from "@/Components/Navigation/SectionNav";
import cn from "@/Helpers/cn";
import { SITE_SECTIONS, sectionCrumbs, sectionShows } from "@/Helpers/siteSections";

const ROW_CLASSES =
    "group flex min-h-14 items-center gap-4 rounded border border-amber-600/40 bg-brown-900/40 px-4 py-3 transition-colors hover:border-amber-500 hover:bg-amber-600/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400";

function CommentCount({ count, hidden }) {
    return (
        <span
            hidden={hidden}
            className="inline-flex items-center gap-1 rounded bg-amber-600/20 px-2 py-1 text-xs font-semibold text-amber-300"
        >
            <Icon icon="comments" style="solid" aria-hidden="true" />
            {count}
            <span className="sr-only">{count === 1 ? " comment" : " comments"}</span>
        </span>
    );
}

function StrategyBadge({ hidden }) {
    return (
        <span
            hidden={hidden}
            className="inline-flex items-center gap-1 rounded border border-amber-600/60 px-2 py-1 text-xs font-semibold text-amber-200"
        >
            <Icon icon="book-open" style="solid" aria-hidden="true" />
            Strategy
        </span>
    );
}

function BossRow({ boss, position, section }) {
    return (
        <Link href={route(`${section}.bosses.show`, { boss: boss.id, slug: boss.slug })} className={ROW_CLASSES}>
            <span
                aria-hidden="true"
                className="flex size-8 shrink-0 items-center justify-center rounded-full border border-amber-600 font-serif text-amber-300"
            >
                {position}
            </span>
            <span className="min-w-0 flex-1 font-semibold break-words text-white">{boss.name}</span>
            <CommentCount count={boss.comments_count ?? 0} hidden={!(sectionShows(section, "itemComments") && boss.comments_count > 0)} />
            <StrategyBadge hidden={!(sectionShows(section, "strategy") && boss.meta?.has_strategy)} />
            <Icon
                icon="chevron-right"
                style="solid"
                aria-hidden="true"
                className="text-amber-500 motion-safe:transition-transform motion-safe:group-hover:translate-x-0.5"
            />
        </Link>
    );
}

export default function Show({ section, raid, bosses }) {
    const raidData = raid.data;
    const sectionConfig = SITE_SECTIONS[section];

    return (
        <Master title={`${raidData.name} - ${sectionConfig.label}`}>
            <SharedHeader
                title={raidData.name}
                subtitle={sectionConfig.label}
                image={raidData.banner_url}
                imagePosition={raidData.banner_position}
                color={raidData.color}
                backgroundClass={sectionConfig.headerBackgroundClass}
            />
            <SectionNav section={section} />
            <PageContainer>
                <Breadcrumbs crumbs={sectionCrumbs(section, [{ label: raidData.name }])} />
                <section aria-labelledby="bosses-heading">
                    <h2 id="bosses-heading" className="mb-4 font-serif text-2xl text-amber-200">
                        Bosses
                    </h2>
                    {bosses.data.length === 0 ? (
                        <EmptyState icon="skull" message="No bosses have been added to this raid yet." />
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {bosses.data.map((boss, index) => (
                                <li key={boss.id}>
                                    <BossRow boss={boss} position={index + 1} section={section} />
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
                <Link
                    href={route(`${section}.raids.trash`, { raid: raidData.id, slug: raidData.slug })}
                    className={cn(ROW_CLASSES, "mt-6", !raidData.has_trash_items && "hidden")}
                >
                    <Icon icon="sack" style="light" aria-hidden="true" className="w-8 text-center text-xl text-amber-400" />
                    <span className="flex-1 font-semibold text-white">Trash items</span>
                    <CommentCount
                        count={raidData.trash_comments_count ?? 0}
                        hidden={!(sectionShows(section, "itemComments") && raidData.trash_comments_count > 0)}
                    />
                    <Icon icon="chevron-right" style="solid" aria-hidden="true" className="text-amber-500" />
                </Link>
            </PageContainer>
        </Master>
    );
}
