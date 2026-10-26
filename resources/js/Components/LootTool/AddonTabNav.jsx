import TabNav from "@/Components/TabNav";

/**
 * The tabs shared by the addon pages: Export, Schema and Settings.
 * `active` names the current tab.
 */
export default function AddonTabNav({ active }) {
    return (
        <TabNav
            ariaLabel="Addon pages"
            scrollable
            currentTab={active}
            tabs={[
                { name: "export", label: "Export", href: route("addons.loot-tool.export") },
                { name: "schema", label: "Schema", href: route("addons.loot-tool.schema") },
                { name: "settings", label: "Settings", href: route("addons.loot-tool.settings") },
            ]}
        />
    );
}
