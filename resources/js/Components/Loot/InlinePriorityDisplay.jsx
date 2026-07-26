export default function InlinePriorityDisplay({ itemId, priorities, weightThreshold = null }) {
    if (!priorities || priorities.length === 0) {
        return <p className="text-center text-gray-500 italic lg:text-right">MS &gt; OS</p>;
    }

    const sorted = [...priorities].sort((a, b) => a.weight - b.weight);
    const grouped = sorted.reduce((acc, priority) => {
        const weight = priority.weight;
        if (!acc[weight]) {
            acc[weight] = [];
        }
        acc[weight].push(priority);
        return acc;
    }, {});

    const allWeights = Object.keys(grouped).sort((a, b) => Number(a) - Number(b));
    const visibleWeights =
        weightThreshold !== null ? allWeights.filter((w) => Number(w) < weightThreshold) : allWeights;
    const hasHidden = visibleWeights.length < allWeights.length;

    return (
        <span className="flex flex-col items-end gap-1">
            {visibleWeights.map((weight, weightIndex) => (
                <span
                    key={`priority-weight-${weight}`}
                    className="flex flex-wrap items-center justify-end gap-x-1 gap-y-0.5"
                >
                    {weightIndex > 0 && <span className="mx-0.5 text-sm font-bold text-amber-600">&gt;</span>}
                    {grouped[weight].map((priority, index) => (
                        <span key={`priority-${itemId}-${priority.id}`} className="inline-flex items-center gap-1">
                            {index > 0 && <span className="mx-0.5 text-sm font-bold text-amber-600">=</span>}
                            <span className="inline-flex items-center gap-1">
                                {priority.media && <img src={priority.media} alt="" className="h-4 w-4" />}
                                <span>{priority.title}</span>
                            </span>
                        </span>
                    ))}
                </span>
            ))}
            {hasHidden && (
                <span className="inline-flex items-center gap-1">
                    <span className="mx-0.5 text-sm font-bold text-amber-600">&gt;</span>
                    <span className="text-gray-500 italic">others</span>
                </span>
            )}
        </span>
    );
}
