import RecordChecklist from "@/Components/Datasets/RecordChecklist";
import Relationships from "@/Components/Datasets/Relationships";
import { SaveButton, firstError } from "@/Components/FormControls";
import NewPhaseForm from "@/Components/GameVersions/NewPhaseForm";
import Pill from "@/Components/Pill";
import useRelationshipForm from "@/Hooks/useRelationshipForm";

/**
 * The title of the other game version that owns a phase, for
 * RecordChecklist's ownerOf, or null when it is unowned or owned by this one.
 */
function otherOwner(phase, gameVersion) {
    return phase.game_version && phase.game_version.id !== gameVersion.id ? phase.game_version.title : null;
}

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
    const { form, setIds, handleSubmit, containerProps } = useRelationshipForm({
        url: route("management.game-versions.update", gameVersion.id),
        key: "phases",
        selected: { phase_ids: phases.selected_ids },
        autosave,
        onSaved,
    });

    return (
        <Relationships
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
                    onChange={(ids) => setIds("phase_ids", ids)}
                    ownerOf={(phase) => otherOwner(phase, gameVersion)}
                    renderLabel={(phase) => <PhaseLabel phase={phase} />}
                    emptyMessage="No phases exist yet. Add the first one below."
                    error={firstError(form.errors, "phase_ids")}
                />
                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>
            <Relationships.AddRecord label="Add a new phase">
                <NewPhaseForm gameVersion={gameVersion} />
            </Relationships.AddRecord>
        </Relationships>
    );
}
