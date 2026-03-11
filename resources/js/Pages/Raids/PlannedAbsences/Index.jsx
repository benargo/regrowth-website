import { useMemo } from "react";
import { usePage, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import PlannedAbsenceRow from "@/Components/PlannedAbsenceRow";
import usePermission from "@/Hooks/Permissions";

function PlannedAbsencesSkeleton() {
    return (
        <div className="animate-pulse space-y-4">
            {[...Array(3)].map((_, i) => (
                <div key={i} className="h-16 rounded bg-brown-800/50" />
            ))}
        </div>
    );
}

export default function Index() {
    const { plannedAbsences } = usePage().props;

    const grouped = useMemo(() => {
        if (!plannedAbsences) {
            return null;
        }

        return plannedAbsences.data.reduce((acc, absence) => {
            const key = absence.character?.name ?? "Unknown Character";
            if (!acc[key]) {
                acc[key] = [];
            }
            acc[key].push(absence);
            return acc;
        }, {});
    }, [plannedAbsences]);

    const isEmpty = grouped && Object.keys(grouped).length === 0;

    return (
        <Master title="Planned Absences">
            <SharedHeader title="Planned Absences" backgroundClass="bg-illidan" />

            <div className="py-8 text-white">
                <div className="container mx-auto px-4">
                    <div className="mb-4 flex flex-row justify-end">
                        {usePermission("create-planned-absences-for-others") && (
                            <Link
                                href={route("raids.absences.create")}
                                className="mt-3 inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800 md:mt-0"
                            >
                                <Icon icon="plus" style="solid" className="mr-1.5 h-4" />
                                Add Absence
                            </Link>
                        )}
                    </div>
                    {!grouped ? (
                        <PlannedAbsencesSkeleton />
                    ) : isEmpty ? (
                        <div className="py-12 text-center text-gray-400">
                            <Icon icon="calendar-times" style="solid" className="mb-4 text-4xl" />
                            <p>No planned absences on record.</p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-6">
                            {Object.entries(grouped).map(([characterName, absences]) => (
                                <div key={characterName}>
                                    <h2 className="mb-3 text-lg font-semibold text-amber-400">{characterName}</h2>
                                    <div className="flex flex-col gap-2">
                                        {absences.map((absence) => (
                                            <PlannedAbsenceRow
                                                key={absence.id}
                                                absence={absence}
                                                showCreatedBy
                                                canEdit
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </Master>
    );
}
