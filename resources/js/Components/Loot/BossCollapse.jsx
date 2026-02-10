import { useState, useRef, useEffect } from "react";
import Icon from "@/Components/FontAwesome/Icon";

export function BossItemsSkeleton() {
    return (
        <div className="animate-pulse space-y-2">
            {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 rounded bg-amber-600/20" />
            ))}
        </div>
    );
}

export default function BossCollapse({ title, bossId, onExpand, loading, children, commentsCount }) {
    const [expanded, setExpanded] = useState(false);
    const hasTriggeredLoad = useRef(false);

    const handleToggle = () => {
        const newExpanded = !expanded;
        setExpanded(newExpanded);

        // Trigger onExpand callback on first expansion
        if (newExpanded && !hasTriggeredLoad.current && onExpand) {
            hasTriggeredLoad.current = true;
            onExpand(bossId);
        }
    };

    // Reset the load trigger when bossId changes (e.g., raid switch)
    useEffect(() => {
        hasTriggeredLoad.current = false;
        setExpanded(false);
    }, [bossId]);

    return (
        <div className="rounded-md border border-amber-600">
            <button
                onClick={handleToggle}
                className="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-amber-600/10"
            >
                <span
                    className={`flex items-center justify-items-center transition-transform duration-500 ${expanded ? "-rotate-180" : ""}`}
                >
                    <Icon icon="chevron-down" style="solid" />
                </span>
                <h3 className="text-lg font-semibold">{title}</h3>
                {commentsCount > 0 && (
                    <span className="ml-auto inline-flex items-center gap-1 rounded bg-amber-600/20 px-2 py-1 text-xs font-semibold text-amber-600">
                        <Icon icon="comments" style="solid" className="h-4 w-4" />
                        {commentsCount}
                    </span>
                )}
            </button>
            {expanded && (
                <div className="border-t border-amber-600 px-4 py-3">
                    {loading ? <BossItemsSkeleton /> : children || <BossItemsSkeleton />}
                </div>
            )}
        </div>
    );
}
