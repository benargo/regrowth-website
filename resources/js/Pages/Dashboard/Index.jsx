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

export default function Index() {
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
                        <DashboardCard href="/dashboard" icon="fal fa-balance-scale-left">
                            Class bias tool
                        </DashboardCard>
                    </div>

                </div>
            </div>
        </Master>
    );
}
