import { Link } from '@inertiajs/react';

export default function TabNav({ tabs, currentTab }) {
    return (
        <div className="border-b border-amber-700 mb-6">
            <nav className="-mb-px flex gap-4">
                {tabs.map((tab) => (
                    <Link
                        key={tab.name}
                        href={tab.href}
                        className={
                            'py-2 px-1 border-b-2 text-sm font-medium transition-colors ' +
                            (currentTab === tab.name
                                ? 'border-primary text-primary'
                                : 'border-transparent text-gray-200 hover:text-primary hover:border-primary')
                        }
                    >
                        {tab.label}
                    </Link>
                ))}
            </nav>
        </div>
    );
}
