import { useState, useRef, useEffect } from "react";
import Icon from "@/Components/FontAwesome/Icon";

function DefaultSkeleton() {
    return (
        <div className="animate-pulse space-y-2">
            {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 rounded bg-gray-600/20" />
            ))}
        </div>
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
    className = "",
    headerClassName = "",
    bodyClassName = "",
}) {
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

    return (
        <div className={`rounded-md border ${className}`}>
            <button
                onClick={handleToggle}
                className={`flex w-full items-center gap-3 px-4 py-3 text-left transition-colors ${headerClassName}`}
            >
                <span
                    className={`flex items-center justify-items-center transition-transform duration-500 ${expanded ? "-rotate-180" : ""}`}
                >
                    <Icon icon="chevron-down" style="solid" />
                </span>
                <h3 className="text-lg font-semibold">{title}</h3>
                {headerRight && <span className="ml-auto">{headerRight}</span>}
            </button>
            {expanded && (
                <div className={`border-t px-4 py-3 ${bodyClassName}`}>
                    {loading ? loadingSkeleton : children || loadingSkeleton}
                </div>
            )}
        </div>
    );
}
