import { useForm } from "@inertiajs/react";
import { SaveButton, firstError } from "@/Components/FormControls";
import { AUTOSAVE_DELAY } from "@/Components/GameVersions/GameVersionForm";
import NewPhaseForm from "@/Components/GameVersions/NewPhaseForm";
import RecordChecklist from "@/Components/GameVersions/RecordChecklist";
import RelationshipPanel, { AddRecordDisclosure } from "@/Components/GameVersions/RelationshipPanel";
import Pill from "@/Components/Pill";
import { useFormAutosave } from "@/Hooks/useAutosave";
import useSyncedSelection from "@/Hooks/useSyncedSelection";

function RaidPill({ raid }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span
                aria-hidden="true"
                className="border-ink-600 h-2.5 w-2.5 shrink-0 rounded-full border"
                style={{ backgroundColor: raid.color ? `#${raid.color}` : "transparent" }}
            />
            <Pill bgColor="bg-ground-800" textColor="text-secondary-200" borderColor="border-ink-600">
                {raid.name} ({raid.difficulty})
            </Pill>
        </span>
    );
}

function PhaseLabel({ phase }) {
    return (
        <span className="flex flex-col gap-2">
            <span className="flex flex-col">
                <span className="text-sm">{phase.description}</span>
            </span>
            {phase.raids.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {phase.raids.map((raid) => (
                        <li key={raid.id}>
                            <RaidPill raid={raid} />
                        </li>
                    ))}
                </ul>
            )}
        </span>
    );
}

export default function PhasesSection({
    gameVersion,
    relationships,
    submitLabel = "Save phases",
    onSaved,
    autosave = false,
}) {
    const { phases } = relationships;
    const form = useForm({ phase_ids: phases.selected_ids });
    useSyncedSelection(form, "phase_ids", phases.selected_ids);

    const linkedPhases = phases.options.filter((phase) => phases.selected_ids.includes(phase.id));

    const url = route("management.game-versions.update", gameVersion.id);
    const { schedule, containerProps } = useFormAutosave({
        form,
        url,
        saved: { phase_ids: phases.selected_ids },
        key: "phases",
        trigger: "blur",
        delay: AUTOSAVE_DELAY,
        enabled: autosave,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
        });
    }

    return (
        <RelationshipPanel
            id="phases"
            title="Phases and raids"
            description="Tick the content phases that belong to this version. Ticking a phase from another version moves it here, along with its raids and bosses. Each phase's raids are listed under it; add a new raid to a linked phase below."
        >
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6" {...containerProps}>
                <RecordChecklist
                    legend="Phases"
                    hideLegend
                    name="phase_ids"
                    options={phases.options}
                    selectedIds={form.data.phase_ids}
                    onChange={(ids) => {
                        form.setData("phase_ids", ids);
                        schedule();
                    }}
                    currentGameVersionId={gameVersion.id}
                    renderLabel={(phase) => <PhaseLabel phase={phase} />}
                    emptyMessage="No phases exist yet. Add the first one below."
                    error={firstError(form.errors, "phase_ids")}
                />
                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>
            <AddRecordDisclosure label="Add a new phase">
                <NewPhaseForm gameVersion={gameVersion} />
            </AddRecordDisclosure>
        </RelationshipPanel>
    );
}
