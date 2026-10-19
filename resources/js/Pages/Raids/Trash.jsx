import { Deferred } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Breadcrumbs from "@/Components/Navigation/Breadcrumbs";
import SectionNav from "@/Components/Navigation/SectionNav";
import ItemList, { ItemListSkeleton } from "@/Components/Loot/ItemList";
import { SITE_SECTIONS, sectionCrumbs } from "@/Helpers/siteSections";

export default function Trash({ section, raid, items, priorityWeightThreshold }) {
    const raidData = raid.data;
    const sectionConfig = SITE_SECTIONS[section];
    const crumbs = sectionCrumbs(section, [
        { label: raidData.name, href: route(`${section}.raids.show`, { raid: raidData.id, slug: raidData.slug }) },
        { label: "Trash items" },
    ]);

    return (
        <Master title={`${raidData.name} trash - ${sectionConfig.label}`}>
            <SharedHeader
                title="Trash items"
                subtitle={raidData.name}
                image={raidData.banner_url}
                imagePosition={raidData.banner_position}
                color={raidData.color}
                backgroundClass={sectionConfig.headerBackgroundClass}
            />
            <SectionNav section={section} />
            <PageContainer>
                <Breadcrumbs crumbs={crumbs} />
                <Deferred data="items" fallback={<ItemListSkeleton />}>
                    <ItemList
                        items={items?.data ?? []}
                        weightThreshold={priorityWeightThreshold}
                        emptyMessage="No trash items drop in this raid."
                    />
                </Deferred>
            </PageContainer>
        </Master>
    );
}
