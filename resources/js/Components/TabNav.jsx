import { Link } from '@inertiajs/react';

export default function TabNav({ tabs, currentTab }) {
    return (
        <div className="border-b border-ink-700 mb-6">
            <nav className="-mb-px flex gap-4">
                {tabs.map((tab) => {
                    // A tab without a URL switches in place, through its onClick.
                    const TabElement = tab.href ? Link : 'button';
                    const elementProps = tab.href ? { href: tab.href } : { type: 'button', onClick: tab.onClick };

                    return (
                        <TabElement
                            key={tab.name}
                            {...elementProps}
                            className={
                                'inline-flex items-center gap-2 py-2 px-1 border-b-2 text-sm font-medium transition-colors ' +
                                (currentTab === tab.name
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-secondary-200 hover:text-primary hover:border-primary')
                            }
                        >
                            {tab.indicator}
                            {tab.label}
                        </TabElement>
                    );
                })}
            </nav>
        </div>
    );
}
