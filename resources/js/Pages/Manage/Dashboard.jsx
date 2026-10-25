import Master from "@/Layouts/Master";
import { Link, usePage } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Collapsible from "@/Components/Collapsible";
import { Can } from "@/Components/Authorizable";
import SharedHeader from "@/Components/SharedHeader";
import Band from "@/Components/Band/Band";
import BandHeading from "@/Components/Band/BandHeading";

/**
 * A linked dashboard tile. `external` renders a plain anchor for pages
 * outside the Inertia app.
 */
function DashboardCard({ href, icon, external = false, children }) {
    const LinkComponent = external ? "a" : Link;

    return (
        <LinkComponent
            href={href}
            className="flex items-center gap-4 rounded border border-amber-600 px-2 py-4 transition-colors hover:bg-amber-600/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
        >
            <div className="mx-2 text-center">
                <Icon icon={icon} style="light" className="text-3xl" />
            </div>
            <div className="mr-2 flex flex-col gap-1">{children}</div>
        </LinkComponent>
    );
}

export default function Dashboard({ discordRoles }) {
    const { auth } = usePage().props;
    const user = auth?.user?.data;

    return (
        <Master title="Officers’ Dashboard">
            <SharedHeader title="Officers’ Dashboard" backgroundClass="bg-officer-meeting" />

            {/* Raiding */}
            <Band tone="light">
                <div className="container mx-auto px-4">
                    <BandHeading level={2} eyebrow="Raiding" className="mb-4">
                        Teams &amp; Attendance
                    </BandHeading>
                    <p className="mb-4 text-amber-400">
                        Manage raid team compositions, planned absences, and attendance tracking.
                    </p>
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("raiding.reports.index")} icon="file-chart-line">
                            <h3 className="text-md">Raid reports</h3>
                            <p className="mb-1 text-sm">View and manage raid reports.</p>
                        </DashboardCard>
                        <Can permission="view-planned-absences">
                            <DashboardCard href={route("raiding.absences.index")} icon="umbrella-beach">
                                <h3 className="text-md">Planned absences</h3>
                                <p className="mb-1 text-sm">Log and review planned absences.</p>
                            </DashboardCard>
                        </Can>
                        <Can permission="view-attendance">
                            <DashboardCard href={route("raiding.attendance.dashboard")} icon="clipboard-list-check">
                                <h3 className="text-md">Attendance tracker</h3>
                                <p className="mb-1 text-sm">Review raid attendance records.</p>
                            </DashboardCard>
                        </Can>
                        <Can permission="manage-raid-plans">
                            <DashboardCard href={route("management.event-templates.index")} icon="copy">
                                <h3 className="text-md">Event templates</h3>
                                <p className="mb-1 text-sm">Create and manage reusable raid event templates.</p>
                            </DashboardCard>
                        </Can>
                    </div>
                </div>
            </Band>

            {/* Loot Council */}
            <Band tone="shaded" edge="both">
                <div className="container mx-auto px-4">
                    <BandHeading level={2} eyebrow="Loot Council" className="mb-4">
                        Distribution &amp; Addons
                    </BandHeading>
                    <p className="mb-4 text-amber-400">Manage loot distribution priorities and addon settings.</p>
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("management.addon.export")} icon="file-export">
                            <h3 className="text-md">Export addon data</h3>
                            <p className="mb-1 text-sm">Generate data files for in-game addons.</p>
                        </DashboardCard>
                        <DashboardCard href={route("management.addon.settings")} icon="cog">
                            <h3 className="text-md">Addon settings</h3>
                            <p className="mb-1 text-sm">Fine tune the addon to the guild’s needs.</p>
                        </DashboardCard>
                    </div>
                </div>
            </Band>

            {/* Datasets */}
            <Band tone="light">
                <div className="container mx-auto px-4">
                    <BandHeading level={2} eyebrow="Datasets" className="mb-4">
                        Game &amp; Guild Data
                    </BandHeading>
                    <p className="mb-4 text-amber-400">Manage core datasets that power the site&rsquo;s features.</p>
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <Can permission="edit-datasets">
                            <DashboardCard href={route("management.game-versions.index")} icon="gamepad">
                                <h3 className="text-md">Manage game versions</h3>
                                <p className="mb-1 text-sm">
                                    Set up the game versions the guild plays, with their realms and API IDs.
                                </p>
                            </DashboardCard>
                        </Can>
                        <Can permission="edit-datasets">
                            <DashboardCard href={route("management.phases.index")} icon="hourglass-start">
                                <h3 className="text-md">Manage phases</h3>
                                <p className="mb-1 text-sm">
                                    Set the content phases of each game version, their start dates and order.
                                </p>
                            </DashboardCard>
                        </Can>
                        <Can permission="edit-datasets">
                            <DashboardCard href={route("management.raids.index")} icon="dungeon">
                                <h3 className="text-md">Manage raids</h3>
                                <p className="mb-1 text-sm">
                                    Set up each game version's raids, their banners and the order of their bosses.
                                </p>
                            </DashboardCard>
                        </Can>
                        <Can permission="edit-datasets">
                            <DashboardCard href={route("management.bosses.index")} icon="skull">
                                <h3 className="text-md">Manage bosses</h3>
                                <p className="mb-1 text-sm">
                                    Add each raid's bosses in encounter order and write their strategies.
                                </p>
                            </DashboardCard>
                        </Can>
                        <Can permission="view-warcraft-logs-guilds">
                            <DashboardCard href={route("management.warcraftlogs.guilds.index")} icon="flag">
                                <h3 className="text-md">Manage Warcraft Logs guilds</h3>
                                <p className="mb-1 text-sm">
                                    Add the guilds reports come from, and choose which of their tags count toward
                                    attendance.
                                </p>
                            </DashboardCard>
                        </Can>
                        <DashboardCard href={route("management.grm.create")} icon="file-upload">
                            <h3 className="text-md">Upload GRM data</h3>
                            <p className="mb-1 text-sm">Upload data from GRM to link mains and alts together.</p>
                        </DashboardCard>
                        <Can permission="update-characters">
                            <DashboardCard href={route("roster.index")} icon="users">
                                <h3 className="text-md">Manage characters</h3>
                                <p className="mb-1 text-sm">
                                    Review and update guild characters, specs, and loot council status.
                                </p>
                            </DashboardCard>
                        </Can>
                    </div>
                </div>
            </Band>

            {/* Site Management */}
            <Band tone="shaded" edge="both">
                <div className="container mx-auto px-4">
                    <BandHeading level={2} eyebrow="Site Management" className="mb-4">
                        Settings &amp; Testing
                    </BandHeading>
                    <p className="mb-4 text-amber-400">Manage site-wide settings and test the site as other roles.</p>

                    <div className="flex flex-col gap-4">
                        {/* Site options */}
                        <Collapsible title="Site options" style="gray">
                            <p className="text-md mb-4 text-gray-400">Configure site-wide options and permissions.</p>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <DashboardCard href={route("management.permissions.index")} icon="shield-check">
                                    <h4 className="text-md">Site permissions</h4>
                                    <p className="mb-1 text-sm">Manage site permissions and access control.</p>
                                </DashboardCard>
                                {user?.admin && (
                                    <DashboardCard href={route("horizon.index")} icon="tachometer-alt" external>
                                        <h4 className="text-md">Scheduled jobs</h4>
                                        <p className="mb-1 text-sm">Background job monitoring and management.</p>
                                    </DashboardCard>
                                )}
                            </div>
                        </Collapsible>

                        {/* Testing */}
                        <Collapsible title="Testing" style="gray">
                            <p className="text-md mb-4 text-gray-400">
                                View the site as different user roles for testing purposes. Use the user menu to switch
                                back to your own account.
                            </p>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <DashboardCard
                                    href={route("auth.view-as", { role: discordRoles.raider })}
                                    icon="user-headset"
                                >
                                    <h4 className="text-md">View site as a Raider</h4>
                                </DashboardCard>
                                <DashboardCard
                                    href={route("auth.view-as", { role: discordRoles.member })}
                                    icon="street-view"
                                >
                                    <h4 className="text-md">View site as a Member</h4>
                                </DashboardCard>
                                <DashboardCard
                                    href={route("auth.view-as", { role: discordRoles.guest })}
                                    icon="user-alien"
                                >
                                    <h4 className="text-md">View site as a Guest</h4>
                                </DashboardCard>
                            </div>
                        </Collapsible>
                    </div>
                </div>
            </Band>
        </Master>
    );
}
