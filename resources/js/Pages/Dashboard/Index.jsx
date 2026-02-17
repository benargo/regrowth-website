import Master from "@/Layouts/Master";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";

function DashboardCard({ href, icon, children }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-4 rounded border border-amber-600 px-2 py-4 transition-colors hover:bg-amber-600/20"
        >
            <div className="mx-2 text-center">
                <Icon icon={icon} style="light" className="text-3xl" />
            </div>
            <div className="mr-2 flex flex-col gap-1">{children}</div>
        </Link>
    );
}

export default function Index({ discordRoles }) {
    return (
        <Master title="Officers’ Dashboard">
            {/* Header */}
            <div className="bg-officer-meeting py-24 text-white">
                <div className="container mx-auto px-4">
                    <h1 className="text-center text-4xl font-bold">Officers&rsquo; Dashboard</h1>
                </div>
            </div>

            {/* Content */}
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Loot Council */}
                    <h2 className="text-2xl font-semibold">Loot Council</h2>
                    <p className="text-md text-gray-400">Manage loot distribution priorities and addon settings.</p>
                    <div className="my-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("loot.index")} icon="balance-scale-left">
                            <h3 className="text-md">Loot bias tool</h3>
                            <p className="mb-1 text-sm">Set priorities to manage loot distribution fairly.</p>
                        </DashboardCard>
                        <DashboardCard href={route("dashboard.addon.export")} icon="file-export">
                            <h3 className="text-md">Export addon data</h3>
                            <p className="mb-1 text-sm">Generate data files for in-game addons.</p>
                        </DashboardCard>
                        <DashboardCard href={route("dashboard.addon.settings")} icon="cog">
                            <h3 className="text-md">Addon settings</h3>
                            <p className="mb-1 text-sm">Fine tune the addon to the guild's needs.</p>
                        </DashboardCard>
                    </div>
                    {/* TODO: Raiding */}
                    {/* <h2 className="text-2xl font-semibold mt-12">Raiding</h2>
                    <p className="text-md text-gray-400">Manage raid team compositions and attendance tracking.</p>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 my-4">
                        <DashboardCard href={route('dashboard.raids.view')} icon="users-class">
                            <h3 className="text-md">Manage raid teams</h3>
                            <p className="text-sm mb-1">Create and modify raid team compositions and assignments.</p>
                        </DashboardCard>
                        <DashboardCard href={route('dashboard.attendance.view')} icon="clipboard-list-check">
                            <h3 className="text-md">Track attendance</h3>
                            <p className="text-sm mb-1">Log and review raid attendance records.</p>
                        </DashboardCard>
                        <DashboardCard href={route('dashboard.attendance.reports')} icon="chart-line">
                            <h3 className="text-md">Attendance reports</h3>
                            <p className="text-sm mb-1">Generate reports on raider attendance over time.</p>
                        </DashboardCard>
                    </div> */}
                    {/* Daily Quests */}
                    <h2 className="mt-12 text-2xl font-semibold">Daily Quests</h2>
                    <p className="text-md text-gray-400">Manage TBC daily quest selections.</p>
                    <div className="my-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("dashboard.daily-quests.form")} icon="scroll">
                            <h3 className="text-md">Set daily quests</h3>
                            <p className="mb-1 text-sm">
                                Choose today&rsquo;s daily quests and post them to Discord.
                            </p>
                        </DashboardCard>
                        <DashboardCard href={route("dashboard.daily-quests.audit")} icon="clipboard-list">
                            <h3 className="text-md">Audit log</h3>
                            <p className="mb-1 text-sm">
                                View who posted, updated, or deleted daily quests.
                            </p>
                        </DashboardCard>
                    </div>
                    {/* Datasets */}
                    <h2 className="mt-12 text-2xl font-semibold">Datasets</h2>
                    <p className="text-md text-gray-400">Manage core datasets that power the site&rsquo;s features.</p>
                    <div className="my-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("dashboard.ranks.view")} icon="chevron-double-up">
                            <h3 className="text-md">Manage guild ranks</h3>
                            <p className="mb-1 text-sm">Match the in-game ranks to the site.</p>
                        </DashboardCard>
                        <DashboardCard href={route("dashboard.phases.view")} icon="hourglass-start">
                            <h3 className="text-md">Manage TBC phases</h3>
                            <p className="mb-1 text-sm">
                                Set the start dates of phases of The Burning Crusade content.
                            </p>
                        </DashboardCard>
                        <DashboardCard href={route("dashboard.grm-upload.form")} icon="file-upload">
                            <h3 className="text-md">Upload GRM data</h3>
                            <p className="mb-1 text-sm">Upload data from GRM to link mains and alts together.</p>
                        </DashboardCard>
                        <DashboardCard href={route("dashboard.permissions.index")} icon="shield-check">
                            <h3 className="text-md">Site permissions</h3>
                            <p className="mb-1 text-sm">Manage site permissions and access control.</p>
                        </DashboardCard>
                    </div>
                    {/* Testing */}
                    <h2 className="mt-12 text-2xl font-semibold">Testing</h2>
                    <p className="text-md text-gray-400">
                        View the site as different user roles for testing purposes. Use the user menu to switch back to
                        your own account.
                    </p>
                    <div className="my-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <DashboardCard href={route("auth.view-as", { role: discordRoles.raider })} icon="user-headset">
                            <h3 className="text-md">View site as a Raider</h3>
                        </DashboardCard>
                        <DashboardCard href={route("auth.view-as", { role: discordRoles.member })} icon="street-view">
                            <h3 className="text-md">View site as a Member</h3>
                        </DashboardCard>
                        <DashboardCard href={route("auth.view-as", { role: discordRoles.guest })} icon="user-alien">
                            <h3 className="text-md">View site as a Guest</h3>
                        </DashboardCard>
                    </div>
                </div>
            </div>
        </Master>
    );
}
