import { Link } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { CartesianGrid, ResponsiveContainer, Scatter, ScatterChart, Tooltip, XAxis, YAxis } from "recharts";

function TooltipBody({ point }) {
    return (
        <div className="flex flex-col gap-0.5 text-left">
            <h3 className="whitespace-nowrap text-sm font-semibold text-amber-300">{point.name}</h3>
            <p className="whitespace-nowrap font-bold">{point.percentage.toFixed(2)}% attendance</p>
            <p className="whitespace-nowrap">
                <span className="font-bold">Raids:</span> {point.raidsTotal}
            </p>
            <p className="whitespace-nowrap">
                <span className="font-bold">Attended:</span> {point.raidsAttended}
            </p>
            <p className="whitespace-nowrap">
                <span className="font-bold">Benched:</span> {point.benched}
            </p>
            <p className="whitespace-nowrap">
                <span className="font-bold">Planned absences:</span> {point.plannedAbsences}
            </p>
            <p className="whitespace-nowrap">
                <span className="font-bold">Other absences:</span> {point.otherAbsences}
            </p>
        </div>
    );
}

function PointShape({ cx, cy, payload }) {
    if (cx == null || cy == null) return null;

    const point = payload.point;
    const iconSize = 20;
    const divRef = useRef(null);
    const [size, setSize] = useState({ width: 200, height: 40 });
    const colorClass = `playable-class-${point.playable_class.name.toLowerCase().replace(/\s+/g, "-")}`;

    useEffect(() => {
        if (!divRef.current) return;
        const observer = new ResizeObserver(([entry]) => {
            const box = entry.borderBoxSize?.[0];
            const width = box ? box.inlineSize : entry.contentRect.width;
            const height = box ? box.blockSize : entry.contentRect.height;
            if (width > 0 && height > 0) {
                setSize({ width, height });
            }
        });
        observer.observe(divRef.current);
        return () => observer.disconnect();
    }, []);

    return (
        <foreignObject
            x={cx - iconSize / 2}
            y={cy - iconSize / 2}
            width={size.width}
            height={size.height}
            overflow="visible"
        >
            <div ref={divRef} className={`bg-${colorClass} inline-flex rounded-sm`}>
                <Link
                    href={route("raids.attendance.matrix", { character: point.id })}
                    className="inline-flex items-center gap-1 p-1"
                >
                    {point.playable_class?.icon_url && (
                        <img
                            src={point.playable_class.icon_url}
                            alt={point.playable_class.name}
                            className="inline-block h-4 w-4 rounded-sm"
                        />
                    )}
                    <span className="hidden whitespace-nowrap text-sm text-gray-900 lg:inline">{point.name}</span>
                </Link>
            </div>
        </foreignObject>
    );
}

function ChartTooltip({ active, payload }) {
    if (!active || !payload?.length) return null;
    const point = payload[0]?.payload?.point;
    if (!point) return null;

    return (
        <div className="rounded bg-gray-900 px-2 py-1 text-xs text-white">
            <TooltipBody point={point} />
        </div>
    );
}

export default function AttendanceScatterChart({ points }) {
    if (!points || points.length === 0) {
        return <p className="mt-3 text-sm text-gray-500">No attendance data yet.</p>;
    }

    // Each point occupies ~10 percentage-point width; find lowest row with no horizontal collision
    const pointWidth = 10;
    const rowOccupied = [];
    const rawData = [...points]
        .sort((a, b) => a.percentage - b.percentage)
        .map((p) => {
            let row = 0;
            while (rowOccupied[row]?.some((end) => p.percentage < end)) {
                row++;
            }
            if (!rowOccupied[row]) {
                rowOccupied[row] = [];
            }
            rowOccupied[row].push(p.percentage + pointWidth);
            return { x: p.percentage, y: row, point: p };
        });
    const maxBucket = rowOccupied.length;
    const data = rawData.map((d) => ({ ...d, y: maxBucket - 1 - d.y }));

    const rowHeight = 38;
    const chartHeight = Math.max(200, maxBucket * rowHeight + 64);

    return (
        <div className="mt-3 w-full overflow-visible" style={{ height: chartHeight }}>
            <ResponsiveContainer width="100%" height="100%">
                <ScatterChart margin={{ top: 16, right: 120, bottom: 32, left: 16 }} style={{ overflow: "visible" }}>
                    <CartesianGrid horizontal={false} stroke="#d97706" strokeOpacity={0.3} />
                    <XAxis
                        type="number"
                        dataKey="x"
                        domain={[0, 100]}
                        ticks={[0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100]}
                        tickFormatter={(value) => (value % 10 === 0 ? value : "")}
                        tick={{ fill: "#fbbf24", fontSize: 12 }}
                        stroke="#92400e"
                        label={{
                            value: "Attendance %",
                            position: "insideBottom",
                            offset: -16,
                            fill: "#9ca3af",
                            fontSize: 12,
                        }}
                    />
                    <YAxis type="number" dataKey="y" domain={[-0.5, maxBucket - 0.5]} hide />
                    <Tooltip content={<ChartTooltip />} cursor={false} isAnimationActive={false} />
                    <Scatter data={data} shape={PointShape} isAnimationActive={false} />
                </ScatterChart>
            </ResponsiveContainer>
        </div>
    );
}
