import { Link } from '@inertiajs/react';

export default function TabNav({ tabs, currentTab, preserveState = false }) {
    return (
        <div className="border-b border-ink-700 mb-6">
            <nav className="-mb-px flex gap-4">
                {tabs.map((tab) => (
                    <Link
                        key={tab.name}
                        href={tab.href}
                        preserveState={preserveState}
                        className={
                            'inline-flex items-center gap-2 py-2 px-1 border-b-2 text-sm font-medium transition-colors ' +
                            (currentTab === tab.name
                                ? 'border-primary text-primary'
                                : 'border-transparent text-secondary-200 hover:text-primary hover:border-primary')
                        }
                    >
                        {tab.indicator}
                        {tab.label}
                    </Link>
                ))}
            </nav>
        </div>
    );
}
