import { useState } from 'react';

export function BossItemsSkeleton() {
    return (
        <div className="space-y-2 animate-pulse">
            {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 bg-amber-600/20 rounded" />
            ))}
        </div>
    );
}

export default function BossCollapse({ title, children }) {
    const [expanded, setExpanded] = useState(false);

    return (
        <div className="border border-amber-600 rounded-md">
            <button
                onClick={() => setExpanded(!expanded)}
                className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-amber-600/10 transition-colors"
            >
                <span className={`flex items-center justify-items-center transition-transform duration-500 ${expanded ? '-rotate-180' : ''}`}>
                    <i className="fas fa-chevron-down"/>
                </span>
                <h3 className="text-lg font-semibold">{title}</h3>
            </button>
            {expanded && (
                <div className="px-4 py-3 border-t border-amber-600">
                    {children || <BossItemsSkeleton />}
                </div>
            )}
        </div>
    );
}