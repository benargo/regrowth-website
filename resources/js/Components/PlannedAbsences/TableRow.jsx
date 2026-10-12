import { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import ConfirmationModal from "@/Components/ConfirmationModal";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import CharacterPortrait from "@/Components/Characters/CharacterPortrait";
import formatDate from "@/Helpers/FormatDate";
import Pill from "@/Components/Pill";
import { TableRow, TableCell } from "@/Components/Table";
import { ActionMenu, ActionMenuLink, ActionMenuButton } from "@/Components/ActionMenu";

/**
 * The short weekday the character is back on: the day after their absence ends.
 */
function returnWeekday(endDate) {
    const [year, month, day] = endDate.split("-").map(Number);
    const returnDate = new Date(year, month - 1, day + 1);

    return returnDate.toLocaleString("en-GB", { weekday: "short" });
}

function ReasonLabel({ reason, className }) {
    return (
        <span className={`flex items-center gap-1.5 ${className ?? ""}`}>
            <Icon icon={reason.icon} style="solid" className="h-[1em] text-sky-400" aria-hidden="true" />
            {reason.label}
        </span>
    );
}

/**
 * One planned absence in the officer table. Owns its own delete confirmation.
 */
export default function PlannedAbsenceTableRow({ absence, isCurrent = false, canDelete = false }) {
    const { auth } = usePage().props;
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const character = absence.character;
    const characterName = character?.name ?? "Unknown Character";
    const isDeleted = Boolean(absence.deleted_at);
    const canRestore = auth.user?.admin ?? false;
    const startLabel = formatDate(absence.start_date).medium;
    const endLabel = absence.end_date ? formatDate(absence.end_date).medium : "Open-ended";
    const returnLabel = isCurrent && absence.end_date ? `back ${returnWeekday(absence.end_date)}` : null;
    const dateClassName = `tabular-nums ${isDeleted ? "line-through" : ""}`;

    const deleteAbsence = () => {
        router.delete(route("raiding.absences.destroy", absence.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(false),
        });
    };

    const restoreAbsence = () => {
        router.post(route("raiding.absences.restore", absence.id), {}, { preserveScroll: true });
    };

    return (
        <TableRow className={isDeleted ? "opacity-60" : ""}>
            <TableCell
                className={`relative ${isCurrent ? "before:bg-ink-500 before:absolute before:inset-y-0 before:left-0 before:w-0.5" : ""}`}
            >
                <div className="flex items-center gap-3">
                    {character && <CharacterPortrait character={character} className="size-9" />}
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <span className="flex items-center gap-2">
                            <span className="text-ink-400 font-medium">{characterName}</span>
                            {isDeleted && (
                                <Pill bgColor="bg-red-900/60" textColor="text-red-300">
                                    Deleted
                                </Pill>
                            )}
                        </span>
                        <span className="text-ink-300/70 text-xs sm:hidden">
                            <span className={dateClassName}>
                                {startLabel} – {endLabel}
                            </span>
                            {returnLabel && <> · {returnLabel}</>}
                        </span>
                        <ReasonLabel reason={absence.reason} className="text-ink-300/70 text-xs md:hidden" />
                    </div>
                </div>
            </TableCell>
            <TableCell className="text-ink-300 hidden whitespace-nowrap sm:table-cell">
                <span className={dateClassName}>{startLabel}</span>
            </TableCell>
            <TableCell className="text-ink-300 hidden whitespace-nowrap sm:table-cell">
                <span className={dateClassName}>{endLabel}</span>
                {returnLabel && <span className="text-ink-400 block text-xs">{returnLabel}</span>}
            </TableCell>
            <TableCell className="text-ink-300 hidden md:table-cell">
                <ReasonLabel reason={absence.reason} />
            </TableCell>
            <TableCell className="text-secondary-300 max-w-md text-sm">
                {absence.notes ? <FormattedMarkdown>{absence.notes}</FormattedMarkdown> : <span>—</span>}
                {absence.created_by && (
                    <p className="text-secondary-500 mt-1 text-xs">
                        Added by {absence.created_by.display_name} on {formatDate(absence.created_at).medium}
                    </p>
                )}
            </TableCell>
            <TableCell className="text-right">
                <ActionMenu label={`Actions for ${characterName}`}>
                    <ActionMenuLink href={route("raiding.absences.edit", absence.id)} icon="pen">
                        Edit
                    </ActionMenuLink>
                    {!isDeleted && canDelete && (
                        <ActionMenuButton onClick={() => setConfirmingDelete(true)} icon="trash">
                            Delete
                        </ActionMenuButton>
                    )}
                    {isDeleted && canRestore && (
                        <ActionMenuButton onClick={restoreAbsence} icon="trash-restore">
                            Restore
                        </ActionMenuButton>
                    )}
                </ActionMenu>
                <ConfirmationModal
                    show={confirmingDelete}
                    onClose={() => setConfirmingDelete(false)}
                    onConfirm={deleteAbsence}
                    title="Delete Planned Absence"
                    confirmLabel="Delete"
                    variant="delete"
                >
                    Are you sure you want to delete this planned absence? This action cannot be undone.
                </ConfirmationModal>
            </TableCell>
        </TableRow>
    );
}
