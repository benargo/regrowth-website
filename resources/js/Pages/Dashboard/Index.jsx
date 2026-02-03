import Master from '@/Layouts/Master';
import { Link } from '@inertiajs/react';

function DashboardCard({ href, icon, children }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-4 border border-amber-600 rounded py-4 px-2 hover:bg-amber-600/20 transition-colors"
        >
            <div className="mx-2 text-center">
                <i className={`${icon} text-3xl`}></i>
            </div>
            <div className="flex flex-col gap-1 mr-2">
                {children}
            </div>
        </Link>
    );
}

export default function Index({ discordRoles }) {
    return (
        <Master title="Officers’ Dashboard">
            {/* Header */}
            <div className="bg-officer-meeting py-24 text-white">
                <div className="container mx-auto px-4">
                    <h1 className="text-4xl font-bold text-center">
                        Officers&rsquo; Dashboard
                    </h1>
                </div>
            </div>

            {/* Content */}
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Loot Council */}
                    <h2 className="text-2xl font-semibold">Loot Council</h2>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 my-4">
                        <DashboardCard href={route('loot.index')} icon="fal fa-balance-scale-left">
                            <h3 className="text-md">Loot bias tool</h3>
                            <p className="text-sm mb-1">Set priorities to manage loot distribution fairly.</p>
                        </DashboardCard>
                        <DashboardCard href={route('dashboard.addon.export')} icon="fal fa-file-export">
                            <h3 className="text-md">Export addon data</h3>
                            <p className="text-sm mb-1">Generate data files for in-game addons.</p>
                        </DashboardCard>
                    </div>
                    {/* Datasets */}
                    <h2 className="text-2xl font-semibold mt-12">Datasets</h2>
                    <p className="text-md text-gray-400">Manage core datasets that power the site&rsquo;s features.</p>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 my-4">
                        <DashboardCard href={route('dashboard.ranks.view')} icon="fal fa-chevron-double-up">
                            <h3 className="text-md">Manage guild ranks</h3>
                            <p className="text-sm mb-1">Match the in-game ranks to the site.</p>
                        </DashboardCard>
                        <DashboardCard href={route('dashboard.phases.view')} icon="fal fa-hourglass-start">
                            <h3 className="text-md">Manage TBC phases</h3>
                            <p className="text-sm mb-1">Set the start dates of phases of The Burning Crusade content.</p>
                        </DashboardCard>
                        <DashboardCard href={route('dashboard.grm-upload.form')} icon="fal fa-file-upload">
                            <h3 className="text-md">Upload GRM data</h3>
                            <p className="text-sm mb-1">Upload data from GRM to link mains and alts together.</p>
                        </DashboardCard>
                    </div>
                    {/* Testing */}
                    <h2 className="text-2xl font-semibold mt-12">Testing</h2>
                    <p className="text-md text-gray-400">View the site as different user roles for testing purposes. Use the user menu to switch back to your own account.</p>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 my-4">
                        <DashboardCard href={route('auth.view-as', { role: discordRoles.raider })} icon="fal fa-user-headset">
                            <h3 className="text-md">View site as a Raider</h3>
                        </DashboardCard>
                        <DashboardCard href={route('auth.view-as', { role: discordRoles.member })} icon="fal fa-street-view">
                            <h3 className="text-md">View site as a Member</h3>
                        </DashboardCard>
                        <DashboardCard href={route('auth.view-as', { role: discordRoles.guest })} icon="fal fa-user-alien">
                            <h3 className="text-md">View site as a Guest</h3>
                        </DashboardCard>
                    </div>
                </div>
            </div>
        </Master>
    );
}
