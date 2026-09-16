import Master from "@/Layouts/Master";
import { Link, usePage } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Collapsible from "@/Components/Collapsible";
import { Can } from "@/Components/Authorizable";
import SharedHeader from "@/Components/SharedHeader";
import Section from "@/Themes/Forever/Section";
import DisplayHeading from "@/Themes/Forever/DisplayHeading";

function DashboardCard({ href, icon, children }) {
    return (
        <Link
            href={href}
            className="border-ink-600 hover:bg-ink-600/20 flex items-center gap-4 rounded border px-2 py-4 transition-colors"
        >
            <div className="mx-2 text-center">
                <Icon icon={icon} style="light" className="text-3xl" />
            </div>
            <div className="mr-2 flex flex-col gap-1">{children}</div>
        </Link>
    );
}

export default function Dashboard({ discordRoles }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <Master title="Officers’ Dashboard">
            <SharedHeader title="Officers' Dashboard" backgroundClass="bg-officer-meeting" />

            {/* Loot Council */}
            <Section tone="mid" edge="bottom" edgeTone="deep">
                <div className="container mx-auto px-4">
                    <DisplayHeading level={2} eyebrow="Loot Council" className="mb-4">
                        Distribution &amp; Addons
                    </DisplayHeading>
                    <p className="text-ink-400 mb-4">Manage loot distribution priorities and addon settings.</p>
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("management.addon.export")} icon="file-export">
                            <h3 className="text-md">Export addon data</h3>
                            <p className="mb-1 text-sm">Generate data files for in-game addons.</p>
                        </DashboardCard>
                        <DashboardCard href={route("management.addon.settings")} icon="cog">
                            <h3 className="text-md">Addon settings</h3>
                            <p className="mb-1 text-sm">Fine tune the addon to the guild's needs.</p>
                        </DashboardCard>
                    </div>
                </div>
            </Section>

            {/* Raiding */}
            <Section tone="deep" edge="bottom" edgeTone="mid">
                <div className="container mx-auto px-4">
                    <DisplayHeading level={2} eyebrow="Raiding" className="mb-4">
                        Teams &amp; Attendance
                    </DisplayHeading>
                    <p className="text-ink-400 mb-4">
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
                        <Can permission="manage-boss-strategies">
                            <DashboardCard href={route("management.boss-strategies.index")} icon="book">
                                <h3 className="text-md">Boss strategies</h3>
                                <p className="mb-1 text-sm">Create and manage boss strategies for raids.</p>
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
            </Section>

            {/* Daily Quests */}
            <Section tone="mid" edge="bottom" edgeTone="deep">
                <div className="container mx-auto px-4">
                    <DisplayHeading level={2} eyebrow="Daily Quests" className="mb-4">
                        TBC Selections
                    </DisplayHeading>
                    <p className="text-ink-400 mb-4">Manage TBC daily quest selections.</p>
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("management.daily-quests.form")} icon="scroll">
                            <h3 className="text-md">Set daily quests</h3>
                            <p className="mb-1 text-sm">Choose today&rsquo;s daily quests and post them to Discord.</p>
                        </DashboardCard>
                        <DashboardCard href={route("management.daily-quests.audit")} icon="clipboard-list">
                            <h3 className="text-md">Audit log</h3>
                            <p className="mb-1 text-sm">View who posted, updated, or deleted daily quests.</p>
                        </DashboardCard>
                    </div>
                </div>
            </Section>

            {/* Site Management */}
            <Section tone="deep">
                <div className="container mx-auto px-4">
                    <DisplayHeading level={2} eyebrow="Site Management" className="mb-4">
                        Settings &amp; Datasets
                    </DisplayHeading>
                    <p className="text-ink-400 mb-4">Manage site-wide settings and core datasets.</p>

                    <div className="flex flex-col gap-4">
                        {/* Datasets */}
                        <Collapsible title="Datasets" style="gray">
                            <p className="text-md text-secondary-400 mb-4">
                                Manage core datasets that power the site&rsquo;s features.
                            </p>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <DashboardCard href={route("management.ranks.view")} icon="chevron-double-up">
                                    <h3 className="text-md">Manage guild ranks</h3>
                                    <p className="mb-1 text-sm">Match the in-game ranks to the site.</p>
                                </DashboardCard>
                                <DashboardCard href={route("management.phases.view")} icon="hourglass-start">
                                    <h3 className="text-md">Manage TBC phases</h3>
                                    <p className="mb-1 text-sm">
                                        Set the start dates of phases of The Burning Crusade content.
                                    </p>
                                </DashboardCard>
                                <DashboardCard href={route("management.grm-upload.form")} icon="file-upload">
                                    <h3 className="text-md">Upload GRM data</h3>
                                    <p className="mb-1 text-sm">
                                        Upload data from GRM to link mains and alts together.
                                    </p>
                                </DashboardCard>
                                <Can permission="update-characters">
                                    <DashboardCard href={route("characters.index")} icon="users">
                                        <h3 className="text-md">Manage characters</h3>
                                        <p className="mb-1 text-sm">
                                            Review and update guild characters, specs, and loot council status.
                                        </p>
                                    </DashboardCard>
                                </Can>
                            </div>
                        </Collapsible>

                        {/* Site options */}
                        <Collapsible title="Site options" style="gray">
                            <p className="text-md text-secondary-400 mb-4">
                                Configure site-wide options and permissions.
                            </p>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <DashboardCard href={route("management.permissions.index")} icon="shield-check">
                                    <h3 className="text-md">Site permissions</h3>
                                    <p className="mb-1 text-sm">Manage site permissions and access control.</p>
                                </DashboardCard>
                                {user?.admin && (
                                    <a
                                        href={route("horizon.index")}
                                        className="border-ink-600 hover:bg-ink-600/20 flex items-center gap-4 rounded border px-2 py-4 transition-colors"
                                    >
                                        <div className="mx-2 text-center">
                                            <Icon icon="tachometer-alt" style="light" className="text-3xl" />
                                        </div>
                                        <div className="mr-2 flex flex-col gap-1">
                                            <h3 className="text-md">Scheduled Jobs</h3>
                                            <p className="mb-1 text-sm">Background job monitoring and management.</p>
                                        </div>
                                    </a>
                                )}
                            </div>
                        </Collapsible>

                        {/* Testing */}
                        <Collapsible title="Testing" style="gray">
                            <p className="text-md text-secondary-400 mb-4">
                                View the site as different user roles for testing purposes. Use the user menu to switch
                                back to your own account.
                            </p>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <DashboardCard
                                    href={route("auth.view-as", { role: discordRoles.raider })}
                                    icon="user-headset"
                                >
                                    <h3 className="text-md">View site as a Raider</h3>
                                </DashboardCard>
                                <DashboardCard
                                    href={route("auth.view-as", { role: discordRoles.member })}
                                    icon="street-view"
                                >
                                    <h3 className="text-md">View site as a Member</h3>
                                </DashboardCard>
                                <DashboardCard
                                    href={route("auth.view-as", { role: discordRoles.guest })}
                                    icon="user-alien"
                                >
                                    <h3 className="text-md">View site as a Guest</h3>
                                </DashboardCard>
                            </div>
                        </Collapsible>
                    </div>
                </div>
            </Section>
        </Master>
    );
}
