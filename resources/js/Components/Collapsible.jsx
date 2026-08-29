import { useState, useRef, useEffect } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";

const STYLES = {
    amber: {
        border: "border-amber-600",
        header: "hover:bg-amber-600/10",
        body: "border-amber-600",
    },
    gray: {
        border: "border-gray-400",
        header: "hover:bg-gray-400/10",
        body: "border-gray-400",
    },
};

export function RotatingChevron({ expanded = false, style = "solid" }) {
    return (
        <span
            className={`flex items-center justify-items-center transition-transform duration-300 ${expanded ? "-rotate-180" : ""}`}
        >
            <Icon icon="chevron-down" style={style} />
        </span>
    );
}

/**
 * A non-interactive, permanently-collapsed rendering of a Collapsible. It mirrors
 * the header shell of the real component but is muted and inert, signalling that
 * the section exists yet is not part of the current context (e.g. a boss the
 * event has not selected).
 *
 * When `tooltip` is supplied, its properties are spread onto a wrapping
 * {@link Tooltip} (e.g. `{ body, position }`). Omit it to render without a
 * tooltip wrapper.
 */
export function DisabledCollapsible({ title, headerRight, tooltip }) {
    const collapsible = (
        <div className="rounded-md border border-gray-700 opacity-60">
            <div className="flex w-full cursor-not-allowed items-center gap-3 px-4 py-3 text-left text-gray-500">
                <span className="flex items-center justify-items-center text-gray-600">
                    <Icon icon="chevron-down" style="solid" />
                </span>
                <h3 className="text-lg font-semibold">{title}</h3>
                {headerRight && <span className="ml-auto">{headerRight}</span>}
            </div>
        </div>
    );

    if (!tooltip) {
        return collapsible;
    }

    return (
        <Tooltip {...tooltip} className="block w-full">
            {collapsible}
        </Tooltip>
    );
}

export default function Collapsible({
    title,
    children,
    initialExpanded = false,
    sessionKey,
    onExpand,
    onCollapse,
    loading = false,
    skeleton,
    headerRight,
    style = "amber",
}) {
    const styles = STYLES[style] ?? STYLES.amber;

    const [expanded, setExpanded] = useState(() => {
        if (sessionKey) {
            try {
                const stored = sessionStorage.getItem(sessionKey);
                if (stored !== null) {
                    return JSON.parse(stored);
                }
            } catch {}
        }
        return initialExpanded;
    });
    const hasTriggeredLoad = useRef(expanded);

    useEffect(() => {
        if (expanded && onExpand) {
            onExpand();
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleToggle = () => {
        const newExpanded = !expanded;
        setExpanded(newExpanded);

        if (sessionKey) {
            try {
                sessionStorage.setItem(sessionKey, JSON.stringify(newExpanded));
            } catch {}
        }

        if (newExpanded) {
            if (!hasTriggeredLoad.current && onExpand) {
                hasTriggeredLoad.current = true;
                onExpand();
            }
        } else {
            onCollapse?.();
        }
    };

    const loadingSkeleton = skeleton || <DefaultSkeleton />;

    function DefaultSkeleton() {
        return (
            <div className="animate-pulse space-y-2">
                {[1, 2, 3].map((i) => (
                    <div key={i} className="h-12 rounded bg-gray-600/20" />
                ))}
            </div>
        );
    }

    return (
        <div className={`rounded-md border ${styles.border}`}>
            <button
                onClick={handleToggle}
                className={`flex w-full items-center gap-3 px-4 py-3 text-left transition-colors ${styles.header}`}
            >
                <RotatingChevron expanded={expanded} />
                <h3 className="text-lg font-semibold">{title}</h3>
                {headerRight && <span className="ml-auto">{headerRight}</span>}
            </button>
            {expanded && (
                <div className={`border-t px-4 py-3 ${styles.body}`}>
                    {loading ? loadingSkeleton : children || loadingSkeleton}
                </div>
            )}
        </div>
    );
}
