import { Deferred } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import AttendanceScatterChart from "@/Components/AttendanceScatterChart";

function BoxLabel({ icon, label }) {
    return (
        <p className="flex items-center gap-2 text-sm text-gray-400">
            {icon && <Icon icon={icon} style="light" className="text-amber-400" />}
            <span>{label}</span>
        </p>
    );
}

function ScatterSkeleton() {
    return (
        <div className="animate-pulse rounded border border-amber-600/30 p-4">
            <div className="mb-3 h-3 w-1/3 rounded bg-gray-700" />
            <div className="h-72 w-full rounded bg-gray-700/50" />
        </div>
    );
}

export default function Graphs({ scatterPoints }) {
    return (
        <Master title="Attendance Graphs">
            <SharedHeader title="Attendance Graphs" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <Deferred data="scatterPoints" fallback={<ScatterSkeleton />}>
                        <div className="rounded border border-amber-600 p-4">
                            <BoxLabel icon="chart-scatter" label="Attendance distribution" />
                            <AttendanceScatterChart points={scatterPoints} />
                        </div>
                    </Deferred>
                </div>
            </div>
        </Master>
    );
}
