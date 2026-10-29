import { router, usePage } from "@inertiajs/react";
import { useEffect, useMemo } from "react";
import TargetMarker from "@/Components/TargetMarker";
import { labelFromSide, colorClassFromSide, textClassFromSide } from "@/Helpers/AssignmentCellHelpers";

function AssignmentCell({ side }) {
    const { label, iconUrl, slug } = labelFromSide(side);
    const colorClass = colorClassFromSide(side);
    const textClass = textClassFromSide(side);

    if (side?.type === "target_marker") {
        return (
            <td className="bg-brown-800/30 px-2">
                <div className="flex items-center justify-center gap-2 text-center text-sm text-white">
                    <TargetMarker marker={slug} />
                </div>
            </td>
        );
    }

    return (
        <td className={`${colorClass || "bg-brown-800/30"} px-2`}>
            <div className={`flex flex-row items-center justify-start gap-2 text-sm ${textClass}`}>
                {iconUrl && <img src={iconUrl} alt={label} className="flex-0 h-7 w-7" />}
                <p className="flex-1">{label}</p>
            </div>
        </td>
    );
}

function AssignmentTable({ assignments, fallbackIconUrl }) {
    return (
        <table className="w-full table-fixed border-collapse">
            <thead>
                <tr className="sr-only">
                    <th className="sr-only px-2 text-left text-sm font-semibold text-amber-500">Left</th>
                    <th className="sr-only px-2 text-left text-sm font-semibold text-amber-500">Right</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-amber-600/20">
                {assignments.map((assignment) => (
                    <tr key={assignment.id} className="h-11">
                        <AssignmentCell side={assignment.left} />
                        <AssignmentCell side={assignment.right} />
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export default function AssignmentGroup({ group = {}, assignments = [] }) {
    const assignmentsToRender = group?.assignments ?? assignments;

    return (
        <div className="overflow-clip rounded border border-amber-600/30 bg-brown-800/50 text-center">
            {group.name && (
                <div className="border-b border-amber-600/30 px-4 py-3">
                    <h3 className="text-sm font-semibold text-amber-500">{group.name}</h3>
                </div>
            )}
            <AssignmentTable assignments={assignmentsToRender} />
        </div>
    );
}
