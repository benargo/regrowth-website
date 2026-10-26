import TabNav from "@/Components/TabNav";

/**
 * The tabs shared by the addon pages: Overview, Export, Schema and Settings.
 * `active` names the current tab.
 */
export default function AddonTabNav({ active }) {
    return (
        <TabNav
            ariaLabel="Addon pages"
            scrollable
            currentTab={active}
            tabs={[
                { name: "overview", label: "Overview", href: route("addons.loot-tool.index") },
                { name: "export", label: "Export", href: route("addons.loot-tool.export") },
                { name: "schema", label: "Schema", href: route("addons.loot-tool.schema") },
                { name: "settings", label: "Settings", href: route("addons.loot-tool.settings") },
            ]}
        />
    );
}
