import { useState, useRef, useEffect, useId } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";

const STYLES = {
    amber: {
        border: "border-ink-600",
        header: "hover:bg-ink-600/10",
        body: "border-ink-600",
    },
    gray: {
        border: "border-secondary-400",
        header: "hover:bg-secondary-400/10",
        body: "border-secondary-400",
    },
};

export function RotatingChevron({ expanded = false, style = "solid" }) {
    return (
        <span
            className={`flex items-center justify-items-center transition-transform duration-300 motion-reduce:transition-none ${expanded ? "-rotate-180" : ""}`}
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
        <div className="border-secondary-700 rounded-md border opacity-60">
            <div className="text-secondary-500 flex w-full cursor-not-allowed items-center gap-3 px-4 py-3 text-left">
                <span className="text-secondary-600 flex items-center justify-items-center">
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
    const panelId = useId();

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
                    <div key={i} className="bg-secondary-600/20 h-12 rounded" />
                ))}
            </div>
        );
    }

    return (
        <div className={`rounded-md border ${styles.border}`}>
            <h3 className="text-lg font-semibold">
                <button
                    type="button"
                    onClick={handleToggle}
                    aria-expanded={expanded}
                    aria-controls={panelId}
                    className={`flex w-full items-center gap-3 px-4 py-3 text-left transition-colors ${styles.header}`}
                >
                    <RotatingChevron expanded={expanded} />
                    <span>{title}</span>
                    {headerRight && <span className="ml-auto text-base font-normal">{headerRight}</span>}
                </button>
            </h3>
            {expanded && (
                <div id={panelId} className={`border-t px-4 py-3 ${styles.body}`}>
                    {loading ? loadingSkeleton : children || loadingSkeleton}
                </div>
            )}
        </div>
    );
}
