import { useMemo, useState } from "react";
import { usePage, Link, router } from "@inertiajs/react";
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import PlannedAbsenceTableRow from "@/Components/PlannedAbsences/TableRow";
import { Can } from "@/Components/Authorizable";
import { usePermission } from "@/Hooks/usePermission";
import useCharacterPortraitChannel from "@/Hooks/useCharacterPortraitChannel";
import ToggleFilter from "@/Components/ToggleFilter";
import { Table, TableHead, TableBody, TableHeader } from "@/Components/Table";
import { linkButtonClassName } from "@/Components/FormControls";
import todayDateString from "@/Helpers/todayDateString";

const SECTION_HEADING = "text-ink-300 font-semibold";

/**
 * Reloads the absences once the server's queued portrait refresh for this
 * character broadcasts. A component so each character joins its own channel.
 */
function PortraitListener({ characterId }) {
    useCharacterPortraitChannel(characterId, () => router.reload({ only: ["planned_absences"] }));

    return null;
}

function PlannedAbsencesSkeleton() {
    return (
        <div className="animate-pulse space-y-3">
            {[...Array(4)].map((_, i) => (
                <div key={i} className="bg-ground-800/50 h-12 rounded" />
            ))}
        </div>
    );
}

function AbsenceTable({ absences, isCurrent = false, canDelete }) {
    return (
        <Table className="mt-3 text-left text-sm">
            <TableHead>
                <tr>
                    <TableHeader>Character</TableHeader>
                    <TableHeader className="hidden sm:table-cell">Start</TableHeader>
                    <TableHeader className="hidden sm:table-cell">End</TableHeader>
                    <TableHeader className="hidden md:table-cell">Reason</TableHeader>
                    <TableHeader>Notes</TableHeader>
                    <TableHeader>
                        <span className="sr-only">Actions</span>
                    </TableHeader>
                </tr>
            </TableHead>
            <TableBody>
                {absences.map((absence) => (
                    <PlannedAbsenceTableRow
                        key={absence.id}
                        absence={absence}
                        isCurrent={isCurrent}
                        canDelete={canDelete}
                    />
                ))}
            </TableBody>
        </Table>
    );
}

export default function Index() {
    const { planned_absences: plannedAbsences } = usePage().props;
    const canDeleteAbsences = usePermission("delete-planned-absences");
    const [showDeleted, setShowDeleted] = useState(false);

    const sections = useMemo(() => {
        if (!plannedAbsences) {
            return null;
        }

        const today = todayDateString();
        const current = [];
        const upcoming = [];
        const past = [];

        plannedAbsences
            .filter((absence) => showDeleted || !absence.deleted_at)
            .forEach((absence) => {
                const end = absence.end_date ?? absence.start_date;

                if (absence.start_date > today) {
                    upcoming.push(absence);
                } else if (end >= today) {
                    current.push(absence);
                } else {
                    past.push(absence);
                }
            });

        return { current, upcoming, past: past.reverse() };
    }, [plannedAbsences, showDeleted]);

    // The server queues portrait refreshes for characters with current or upcoming absences.
    const refreshingCharacterIds = useMemo(() => {
        if (!sections) {
            return [];
        }

        const characterIds = [...sections.current, ...sections.upcoming]
            .filter((absence) => !absence.deleted_at && absence.character)
            .map((absence) => absence.character.id);

        return [...new Set(characterIds)];
    }, [sections]);

    const isEmpty = plannedAbsences?.length === 0;

    return (
        <Master title="Planned Absences">
            <SharedHeader title="Planned Absences" backgroundClass="bg-illidan" />

            {refreshingCharacterIds.map((characterId) => (
                <PortraitListener key={characterId} characterId={characterId} />
            ))}

            <PageContainer>
                <div className="mb-6 flex flex-row flex-wrap items-center justify-end gap-6">
                    <ToggleFilter label="Show deleted" value={showDeleted} onChange={setShowDeleted} />
                    <Can permission="create-planned-absences">
                        <Link href={route("raiding.absences.create")} className={linkButtonClassName}>
                            <Icon icon="plus" style="solid" className="h-4" />
                            Add absence
                        </Link>
                    </Can>
                </div>

                {!sections ? (
                    <PlannedAbsencesSkeleton />
                ) : isEmpty ? (
                    <EmptyState icon="calendar-times" message="No planned absences on record." />
                ) : (
                    <div className="flex flex-col gap-10">
                        {sections.current.length > 0 && (
                            <section>
                                <h2 className={SECTION_HEADING}>Away now</h2>
                                <AbsenceTable absences={sections.current} isCurrent canDelete={canDeleteAbsences} />
                            </section>
                        )}

                        <section>
                            <h2 className={SECTION_HEADING}>Coming up</h2>
                            {sections.upcoming.length > 0 ? (
                                <AbsenceTable absences={sections.upcoming} canDelete={canDeleteAbsences} />
                            ) : (
                                <p className="text-secondary-500 mt-3 text-sm">No one has an absence booked.</p>
                            )}
                        </section>

                        {sections.past.length > 0 && (
                            <Disclosure as="section">
                                <DisclosureButton className={`group flex items-center gap-2 ${SECTION_HEADING}`}>
                                    <Icon
                                        icon="chevron-right"
                                        style="solid"
                                        className="h-3 transition-transform group-data-open:rotate-90"
                                    />
                                    Past absences ({sections.past.length})
                                </DisclosureButton>
                                <DisclosurePanel>
                                    <AbsenceTable absences={sections.past} canDelete={canDeleteAbsences} />
                                </DisclosurePanel>
                            </Disclosure>
                        )}
                    </div>
                )}
            </PageContainer>
        </Master>
    );
}
