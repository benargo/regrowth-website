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
            <div className="mr-2">
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
                            Loot bias tool
                        </DashboardCard>
                        <DashboardCard href={route('dashboard.addon.export')} icon="fal fa-file-export">
                            Export addon data
                        </DashboardCard>
                    </div>
                    {/* Raids and phases */}
                    <h2 className="text-2xl font-semibold mt-12">Raids and Phases</h2>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 my-4">
                        <DashboardCard href={route('dashboard.manage-phases')} icon="fal fa-hourglass-start">
                            Manage TBC phases
                        </DashboardCard>
                    </div>
                    {/* Testing */}
                    <h2 className="text-2xl font-semibold mt-12">Testing</h2>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 my-4">
                        <DashboardCard href={route('auth.view-as', { role: discordRoles.raider })} icon="fal fa-user-headset">
                            View site as a Raider
                        </DashboardCard>
                        <DashboardCard href={route('auth.view-as', { role: discordRoles.member })} icon="fal fa-street-view">
                            View site as a Member
                        </DashboardCard>
                        <DashboardCard href={route('auth.view-as', { role: discordRoles.guest })} icon="fal fa-user-alien">
                            View site as a Guest
                        </DashboardCard>
                    </div>
                </div>
            </div>
        </Master>
    );
}
