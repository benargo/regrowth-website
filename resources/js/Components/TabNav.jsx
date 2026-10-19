import { useEffect, useRef } from "react";
import { Link } from "@inertiajs/react";
import cn from "@/Helpers/cn";

/**
 * Underlined tabs. A tab with `href` renders a link; one without switches in place through `onClick`.
 * The current tab carries `aria-current="page"`. With `scrollable`, the row scrolls sideways when it
 * doesn't fit and keeps the current tab in view. `className` replaces the wrapper's bottom margin.
 */
export default function TabNav({ tabs, currentTab, ariaLabel, scrollable = false, className = "mb-6" }) {
    const navRef = useRef(null);

    useEffect(() => {
        if (!scrollable) {
            return;
        }

        const nav = navRef.current;
        const current = nav?.querySelector('[aria-current="page"]');

        if (current) {
            nav.scrollLeft = current.offsetLeft - (nav.clientWidth - current.offsetWidth) / 2;
        }
    }, [scrollable, currentTab]);

    return (
        <div className={cn("border-b border-amber-700", className)}>
            <nav
                ref={navRef}
                aria-label={ariaLabel}
                className={cn("-mb-px flex gap-4", scrollable && "relative overflow-x-auto [scrollbar-width:none]")}
            >
                {tabs.map((tab) => {
                    const TabElement = tab.href ? Link : "button";
                    const elementProps = tab.href ? { href: tab.href } : { type: "button", onClick: tab.onClick };

                    const isCurrent = currentTab === tab.name;

                    return (
                        <TabElement
                            key={tab.name}
                            {...elementProps}
                            aria-current={isCurrent ? "page" : undefined}
                            style={tab.style}
                            className={cn(
                                "inline-flex min-h-9 items-center gap-2 border-b-2 px-1 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                                scrollable && "shrink-0 whitespace-nowrap",
                                isCurrent
                                    ? tab.activeClassName || "border-primary text-primary"
                                    : "border-transparent text-gray-200 hover:border-primary hover:text-primary",
                            )}
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
