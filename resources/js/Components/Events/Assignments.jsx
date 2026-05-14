import { router, usePage } from "@inertiajs/react";
import { useEffect, useMemo } from "react";
import TargetMarker from "@/Components/TargetMarker";

function mapAssignmentToType({ assignment, fallbackIconUrl }) {
    switch (assignment.type) {
        case "character":
            return <AssignmentCharacter character={assignment.data} />;
        case "playable_class":
            return <AssignmentPlayableClass playableClass={assignment.data} />;
        case "spell":
            return <AssignmentSpell spell={assignment.data} fallbackIconUrl={fallbackIconUrl} />;
        case "target_marker":
            return <AssignmentTargetMarker marker={assignment.data} />;
        default:
            return <AssignmentScalar value={assignment.data} />;
    }
}

function AssignmentTable({ assignments, fallbackIconUrl }) {
    return (
        <table className="w-full table-fixed border-collapse">
            <thead>
                <tr>
                    <th className="sr-only px-4 py-3 text-left text-sm font-semibold text-amber-500">Left</th>
                    <th className="sr-only px-4 py-3 text-left text-sm font-semibold text-amber-500">Right</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-amber-600/20">
                {assignments.map((assignment) => (
                    <tr key={assignment.id}>
                        {mapAssignmentToType({ assignment: assignment.left, fallbackIconUrl })}
                        {mapAssignmentToType({ assignment: assignment.right, fallbackIconUrl })}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function AssignmentCharacter({ character }) {
    return (
        <td className={`bg-playable-class-${character.playable_class.slug} px-4 py-3 text-center text-sm text-black`}>
            {character.name}
        </td>
    );
}

function AssignmentPlayableClass({ playableClass }) {
    return (
        <td className={`bg-playable-class-${playableClass.slug} px-4 py-3 text-center text-sm text-black`}>
            {playableClass.name}
        </td>
    );
}

function AssignmentSpell({ spell, fallbackIconUrl }) {
    return (
        <td className={`bg-${spell.color}/40 p-x-4 py-3`}>
            <div className="flex items-center justify-center gap-2 text-sm text-white">{spell.name}</div>
        </td>
    );
}

function AssignmentTargetMarker({ marker }) {
    return (
        <td className="bg-brown-800/30 px-4">
            <div className="flex items-center justify-center gap-2 text-center text-sm text-white">
                <TargetMarker marker={marker.slug} />
            </div>
        </td>
    );
}

function AssignmentScalar({ value }) {
    return (
        <td className="h-4 bg-brown-800/30 px-4 py-3">
            <div className="text-center text-sm text-white">{value}</div>
        </td>
    );
}

export default function AssignmentGroup({ group = {}, assignments = [] }) {
    const assignmentsToRender = group?.assignments ?? assignments;
    const { questionMarkIconUrl } = usePage().props;

    const needsFallback = useMemo(
        () =>
            assignmentsToRender.some(
                (a) =>
                    (a.left?.type === "spell" && !a.left?.data?.icon) ||
                    (a.right?.type === "spell" && !a.right?.data?.icon),
            ),
        [assignmentsToRender],
    );

    useEffect(() => {
        if (needsFallback && !questionMarkIconUrl) {
            router.reload({ only: ["questionMarkIconUrl"] });
        }
    }, [needsFallback, questionMarkIconUrl]);

    return (
        <div className="overflow-clip rounded border border-amber-600/30 bg-brown-800/50 text-center">
            {group.name && (
                <div className="border-b border-amber-600 px-4 py-3">
                    <h3 className="text-sm font-semibold text-amber-500">{group.name}</h3>
                </div>
            )}
            <AssignmentTable assignments={assignmentsToRender} fallbackIconUrl={questionMarkIconUrl} />
        </div>
    );
}
