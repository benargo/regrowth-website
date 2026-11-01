import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import TabNav from "@/Components/TabNav";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Icon from "@/Components/FontAwesome/Icon";

export const NUMBER_FORMAT = new Intl.NumberFormat("en-GB");

export const LINK_CLASS =
    "rounded-sm text-sm text-amber-500 underline-offset-4 hover:text-amber-400 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400";

/**
 * The shell shared by the permissions tabs: the "Permissions" header, a back link to the officers'
 * dashboard, an intro line linking to the Discord Roles page, and the Access / Permissions / Groups
 * tab nav with `currentTab` marked current. `children` render beneath the tabs.
 */
export default function PermissionsPage({ currentTab, children }) {
    return (
        <Master title="Permissions">
            <SharedHeader title="Permissions" backgroundClass="bg-arcatraz" />
            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink
                        href={route("management.dashboard")}
                        className="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to officers' dashboard
                    </ToolNavLink>
                </div>
            </ToolNav>
            <PageContainer>
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <p className="text-gray-400">
                        Choose what each Discord role can do on the site, and how permissions are named and grouped.
                    </p>
                    <Link href={route("management.discord-roles.index")} className={LINK_CLASS}>
                        Choose which Discord roles grant permissions
                    </Link>
                </div>

                <TabNav
                    ariaLabel="Permissions sections"
                    currentTab={currentTab}
                    tabs={[
                        { name: "access", label: "Access", href: route("management.permissions.index") },
                        { name: "permissions", label: "Permissions", href: route("management.permissions.manage") },
                        { name: "groups", label: "Groups", href: route("management.permission-groups.index") },
                    ]}
                />

                {children}
            </PageContainer>
        </Master>
    );
}
