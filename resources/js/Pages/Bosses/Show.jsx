import { Deferred, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import TabNav from "@/Components/TabNav";
import Icon from "@/Components/FontAwesome/Icon";
import BossStrategy from "@/Components/Bosses/BossStrategy";
import Breadcrumbs from "@/Components/Navigation/Breadcrumbs";
import SectionNav from "@/Components/Navigation/SectionNav";
import ItemList, { ItemListSkeleton } from "@/Components/Loot/ItemList";
import { Can } from "@/Components/Authorizable";
import { SITE_SECTIONS, sectionCrumbs, sectionShows } from "@/Helpers/siteSections";

export default function Show({ section, tab, boss, items, priorityWeightThreshold }) {
    const bossData = boss.data;
    const raid = bossData.raid;
    const sectionConfig = SITE_SECTIONS[section];
    const shouldShowStrategy = sectionShows(section, "strategy");
    const bossRoute = (query = {}) =>
        route(`${section}.bosses.show`, { boss: bossData.id, slug: bossData.slug, ...query });
    const crumbs = sectionCrumbs(section, [
        { label: raid.name, href: route(`${section}.raids.show`, { raid: raid.id, slug: raid.slug }) },
        { label: bossData.name },
    ]);

    const itemsSection = (
        <section aria-labelledby="boss-items-heading">
            <h2
                id="boss-items-heading"
                className={shouldShowStrategy ? "sr-only" : "mb-4 font-serif text-2xl text-amber-200"}
            >
                Items
            </h2>
            <Deferred data="items" fallback={<ItemListSkeleton />}>
                <ItemList
                    items={items?.data ?? []}
                    weightThreshold={priorityWeightThreshold}
                    emptyMessage="No items drop from this boss."
                />
            </Deferred>
        </section>
    );

    return (
        <Master title={`${bossData.name} - ${sectionConfig.label}`}>
            <SharedHeader
                title={bossData.name}
                subtitle={raid.name}
                image={raid.banner_url}
                imagePosition={raid.banner_position}
                color={raid.color}
                backgroundClass={sectionConfig.headerBackgroundClass}
            />
            <SectionNav section={section} />
            <PageContainer>
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <Breadcrumbs crumbs={crumbs} className="" />
                    <Can permission="edit-datasets">
                        <Link
                            href={route("management.bosses.edit", { boss: bossData.id })}
                            className="inline-flex min-h-9 items-center gap-2 rounded border border-amber-600 px-4 py-2 text-sm text-gray-200 transition-colors hover:bg-amber-600/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                        >
                            <Icon icon="pencil" aria-hidden="true" />
                            Edit boss
                        </Link>
                    </Can>
                </div>
                {shouldShowStrategy ? (
                    <>
                        <TabNav
                            ariaLabel={`${bossData.name} views`}
                            currentTab={tab}
                            tabs={[
                                { name: "strategy", label: "Strategy", href: bossRoute() },
                                { name: "items", label: "Items", href: bossRoute({ tab: "items" }) },
                            ]}
                        />
                        {tab === "strategy" ? (
                            <section aria-label="Strategy">
                                <BossStrategy boss={bossData} showEditLink={false} />
                            </section>
                        ) : (
                            itemsSection
                        )}
                    </>
                ) : (
                    itemsSection
                )}
            </PageContainer>
        </Master>
    );
}
