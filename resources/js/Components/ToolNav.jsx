import { Link } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import Icon from "@/Components/FontAwesome/Icon";

export function ToolNavSteps({ label = "Progress", steps, currentKey }) {
    const firstSegmentShape = "[clip-path:polygon(0_0,calc(100%-12px)_0,100%_50%,calc(100%-12px)_100%,0_100%)]";
    const segmentShape = "[clip-path:polygon(0_0,calc(100%-12px)_0,100%_50%,calc(100%-12px)_100%,0_100%,12px_50%)]";

    const currentIndex = steps.findIndex((step) => step.key === currentKey);
    const listRef = useRef(null);

    useEffect(() => {
        const list = listRef.current;
        const current = list?.querySelector('[aria-current="step"]');

        if (current) {
            list.scrollLeft = current.offsetLeft - (list.clientWidth - current.offsetWidth) / 2;
        }
    }, [currentKey]);

    return (
        <ol
            ref={listRef}
            aria-label={label}
            className="relative -mx-4 flex min-w-0 flex-1 [scrollbar-width:none] overflow-x-auto px-4 py-2"
        >
            {steps.map((step, index) => {
                const isCurrent = index === currentIndex;
                const isPast = index < currentIndex;
                const Component = step.href ? Link : "span";
                const linkProps = step.href ? { href: step.href } : {};

                return (
                    <li key={step.key} className={`shrink-0 ${index > 0 ? "-ml-2" : ""}`}>
                        <Component
                            {...linkProps}
                            aria-current={isCurrent ? "step" : undefined}
                            className={
                                "group focus-visible:outline-ink-400 relative flex h-9 items-center gap-2 pr-6 text-sm whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 " +
                                (index === 0 ? "pl-4 " : "pl-6 ") +
                                (isCurrent
                                    ? "font-semibold text-white"
                                    : isPast
                                      ? "text-ink-200 font-medium"
                                      : "text-secondary-400 font-medium")
                            }
                        >
                            <span
                                aria-hidden="true"
                                className={
                                    "absolute inset-0 " +
                                    (index === 0 ? firstSegmentShape : segmentShape) +
                                    " " +
                                    (isCurrent
                                        ? "bg-ground-600"
                                        : (isPast ? "bg-ground-800" : "bg-ground-800/50") +
                                          (step.href ? " group-hover:bg-ground-700" : ""))
                                }
                            />
                            {isPast && <Icon icon="check" className="relative" />}
                            <span className="relative">{step.label}</span>
                        </Component>
                    </li>
                );
            })}
        </ol>
    );
}

export function ToolNavLink({ as: Component = Link, className = "", children, ...props }) {
    return (
        <Component
            className={`hover:border-primary hover:bg-ground-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white ${className}`}
            {...props}
        >
            {children}
        </Component>
    );
}

export default function ToolNav({ children }) {
    return (
        <nav className="bg-ground-900 shadow">
            <div className="container mx-auto px-4">
                <div className="flex min-h-12 flex-row flex-wrap items-center justify-between gap-x-2">{children}</div>
            </div>
        </nav>
    );
}
