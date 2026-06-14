import { useMemo } from "react";
import { usePage, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import PlannedAbsenceRow from "@/Components/PlannedAbsences/Row";
import { Can } from "@/Components/Authorizable";

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
    const { planned_absences: plannedAbsences } = usePage().props;

    const grouped = useMemo(() => {
        if (!plannedAbsences) {
            return null;
        }

        return plannedAbsences.reduce((acc, absence) => {
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

            <PageContainer>
                    <div className="mb-4 flex flex-row justify-end">
                        <Can permission="manage-planned-absences">
                            <Link
                                href={route("raiding.absences.create")}
                                className="mt-3 inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-hidden focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800 md:mt-0"
                            >
                                <Icon icon="plus" style="solid" className="mr-1.5 h-4" />
                                Add Absence
                            </Link>
                        </Can>
                    </div>
                    {!grouped ? (
                        <PlannedAbsencesSkeleton />
                    ) : isEmpty ? (
                        <EmptyState icon="calendar-times" message="No planned absences on record." />
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
            </PageContainer>
        </Master>
    );
}
