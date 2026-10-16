import { Link } from '@inertiajs/react';

/**
 * Underlined tabs. A tab with `href` renders a link; one without switches in place through `onClick`.
 * The current tab carries `aria-current="page"`.
 */
export default function TabNav({ tabs, currentTab, ariaLabel }) {
    return (
        <div className="border-b border-ink-700 mb-6">
            <nav aria-label={ariaLabel} className="-mb-px flex gap-4">
                {tabs.map((tab) => {
                    // A tab without a URL switches in place, through its onClick.
                    const TabElement = tab.href ? Link : 'button';
                    const elementProps = tab.href ? { href: tab.href } : { type: 'button', onClick: tab.onClick };

                    const isCurrent = currentTab === tab.name;

                    return (
                        <TabElement
                            key={tab.name}
                            {...elementProps}
                            aria-current={isCurrent ? 'page' : undefined}
                            style={tab.style}
                            className={
                                'inline-flex items-center gap-2 py-2 px-1 border-b-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink-400 ' +
                                (isCurrent
                                    ? tab.activeClassName || 'border-primary text-primary'
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
