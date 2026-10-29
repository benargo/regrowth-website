import { useMemo, useState } from "react";
import { usePage, Link, router } from "@inertiajs/react";
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import SectionNav from "@/Components/Navigation/SectionNav";
import PageContainer from "@/Components/PageContainer";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import PlannedAbsenceTableRow from "@/Components/PlannedAbsences/TableRow";
import { Can } from "@/Components/Authorizable";
import useCharacterPortraitChannel from "@/Hooks/useCharacterPortraitChannel";
import ToggleFilter from "@/Components/ToggleFilter";
import { Table, TableHead, TableBody, TableHeader } from "@/Components/Table";
import { linkButtonClassName } from "@/Components/FormControls";

const SECTION_HEADING = "text-amber-300 font-semibold";
const NO_PERMISSIONS = { update: false, delete: false, restore: false };

/**
 * Reloads the portrait map once the server's queued portrait refresh for this
 * character broadcasts. A component so each character joins its own channel.
 */
function PortraitListener({ characterId }) {
    useCharacterPortraitChannel(characterId, () => router.reload({ only: ["character_portraits"] }));

    return null;
}

function PlannedAbsencesSkeleton() {
    return (
        <div className="animate-pulse space-y-3">
            {[...Array(4)].map((_, i) => (
                <div key={i} className="bg-brown-800/50 h-12 rounded" />
            ))}
        </div>
    );
}

function AbsenceTable({ absences, isCurrent = false, permissions }) {
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
                        canUpdate={permissions.update}
                        canDelete={permissions.delete}
                        canRestore={permissions.restore}
                    />
                ))}
            </TableBody>
        </Table>
    );
}

export default function Index() {
    const {
        planned_absences: plannedAbsencesResource,
        past_planned_absences: pastPlannedAbsences,
        character_portraits: characterPortraits,
    } = usePage().props;
    const permissions = plannedAbsencesResource?.permissions ?? NO_PERMISSIONS;
    const [showDeleted, setShowDeleted] = useState(false);

    // Current and upcoming absences load live; past ones come from the server's daily cache.
    const plannedAbsences = useMemo(() => {
        if (!plannedAbsencesResource || !pastPlannedAbsences) {
            return null;
        }

        return [...plannedAbsencesResource.data, ...pastPlannedAbsences].map((absence) => {
            const characterId = absence.character?.id;

            if (!characterPortraits || !(characterId in characterPortraits)) {
                return absence;
            }

            return {
                ...absence,
                character: { ...absence.character, portrait_url: characterPortraits[characterId] },
            };
        });
    }, [plannedAbsencesResource, pastPlannedAbsences, characterPortraits]);

    const sections = useMemo(() => {
        if (!plannedAbsences) {
            return null;
        }

        const current = [];
        const upcoming = [];
        const past = [];

        // The server decides which absences have started or ended, so grouping follows its date, not the browser's.
        plannedAbsences
            .filter((absence) => showDeleted || !absence.deleted_at)
            .forEach((absence) => {
                if (!absence.meta.has_started) {
                    upcoming.push(absence);
                } else if (!absence.meta.has_ended) {
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
            <SectionNav section="raiding" />

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
                                <AbsenceTable absences={sections.current} isCurrent permissions={permissions} />
                            </section>
                        )}

                        <section>
                            <h2 className={SECTION_HEADING}>Coming up</h2>
                            {sections.upcoming.length > 0 ? (
                                <AbsenceTable absences={sections.upcoming} permissions={permissions} />
                            ) : (
                                <p className="text-gray-400 mt-3 text-sm">No one has an absence booked.</p>
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
                                    <AbsenceTable absences={sections.past} permissions={permissions} />
                                </DisclosurePanel>
                            </Disclosure>
                        )}
                    </div>
                )}
            </PageContainer>
        </Master>
    );
}
