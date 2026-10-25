import TabNav from "@/Components/TabNav";
import { SITE_SECTIONS, currentSectionItemKey } from "@/Helpers/siteSections";
import { usePermissionCheck } from "@/Hooks/usePermission";

/**
 * The second-level navigation bar under the page header on every Raiding and Loot page.
 */
export default function SectionNav({ section }) {
    const hasPermission = usePermissionCheck();
    const items = SITE_SECTIONS[section].items.filter((item) => !item.permission || hasPermission(item.permission));

    return (
        <div className="bg-brown-900 shadow">
            <div className="container mx-auto px-4">
                <TabNav
                    ariaLabel={SITE_SECTIONS[section].label}
                    currentTab={currentSectionItemKey(section)}
                    scrollable
                    className="border-b-0 pt-1"
                    tabs={items.map((item) => ({ name: item.key, label: item.label, href: route(item.routeName) }))}
                />
            </div>
        </div>
    );
}
