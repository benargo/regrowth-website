import { Can } from "@/Components/Authorizable";
import Icon from "@/Components/FontAwesome/Icon";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";

/**
 * The officer tool bar shared by the loot tool pages: a link to the Overview on the left,
 * and links to the Export, Schema and Settings pages on the right. Shown only to officers.
 */
export default function LootToolNav() {
    return (
        <Can permission="view-officer-dashboard">
            <ToolNav>
                <ToolNavLink href={route("addons.loot-tool.index")}>
                    <Icon icon="info-circle" style="regular" className="mr-2 text-xs" />
                    Overview
                </ToolNavLink>
                <div className="flex flex-row flex-wrap items-center gap-x-2">
                    <span className="text-sm text-gray-400">Officer tools</span>
                    <ToolNavLink href={route("addons.loot-tool.export")}>
                        <Icon icon="file-export" style="solid" className="mr-2 text-xs" />
                        Export
                    </ToolNavLink>
                    <ToolNavLink href={route("addons.loot-tool.schema")}>
                        <Icon icon="brackets-curly" style="solid" className="mr-2 text-xs" />
                        Schema
                    </ToolNavLink>
                    <ToolNavLink href={route("addons.loot-tool.settings")}>
                        <Icon icon="cog" style="solid" className="mr-2 text-xs" />
                        Settings
                    </ToolNavLink>
                </div>
            </ToolNav>
        </Can>
    );
}
