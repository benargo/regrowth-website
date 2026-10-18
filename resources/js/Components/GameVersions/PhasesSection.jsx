import { Link } from "@inertiajs/react";
import RecordChecklist from "@/Components/Datasets/RecordChecklist";
import Relationships from "@/Components/Datasets/Relationships";
import { SaveButton, firstError, linkClassName } from "@/Components/FormControls";
import UnscheduledPhaseOrder from "@/Components/Phases/UnscheduledPhaseOrder";
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
                className="h-2.5 w-2.5 shrink-0 rounded-full border border-amber-600"
                style={{ backgroundColor: raid.color ? `#${raid.color}` : "transparent" }}
            />
            <Pill bgColor="bg-brown-800" textColor="text-gray-200" borderColor="border-amber-600">
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
    routes,
    submitLabel = "Save phases",
    onSaved,
    autosave = false,
}) {
    const { phases } = relationships;
    const linkedPhases = phases.data.filter((phase) => phases.selected_ids.includes(phase.id));
    const { form, setIds, handleSubmit, containerProps } = useRelationshipForm({
        url: routes.update,
        key: "phases",
        selected: { phase_ids: phases.selected_ids },
        autosave,
        onSaved,
    });

    return (
        <Relationships
            id="phases"
            title="Phases and raids"
            description="Tick the content phases that belong to this version. Ticking a phase from another version moves it here, along with its raids and bosses."
        >
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6" {...containerProps}>
                <RecordChecklist
                    legend="Phases"
                    hideLegend
                    name="phase_ids"
                    options={phases.data}
                    selectedIds={form.data.phase_ids}
                    onChange={(ids) => setIds("phase_ids", ids)}
                    ownerOf={(phase) => otherOwner(phase, gameVersion)}
                    renderLabel={(phase) => <PhaseLabel phase={phase} />}
                    emptyMessage="No phases exist yet. Add the first one from the phases page."
                    error={firstError(form.errors, "phase_ids")}
                />
                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>

            {relationships.unscheduled_phases.data.length > 1 && (
                <div className="flex flex-col gap-3">
                    <h3 className="font-serif text-xl text-white">Order of unscheduled phases</h3>
                    <p className="max-w-prose text-sm text-gray-300">
                        Drag this version's phases without a start date into the order you expect them to arrive.
                    </p>
                    <UnscheduledPhaseOrder
                        phases={relationships.unscheduled_phases.data}
                        url={routes.update}
                        field="unscheduled_phase_ids"
                        label={`Unscheduled ${gameVersion.title} phases`}
                        className="divide-y divide-amber-600/40 rounded border border-amber-600/40"
                        renderPhase={(phase, dragHandle) => (
                            <div className="bg-brown-900 flex items-center gap-3 px-3 py-2">
                                {dragHandle}
                                <span className="text-white">{phase.name}</span>
                                <span className="truncate text-sm text-gray-300">{phase.description}</span>
                            </div>
                        )}
                    />
                </div>
            )}

            {linkedPhases.length > 0 && (
                <div className="flex flex-col gap-2">
                    <h3 className="font-serif text-xl text-white">Add a raid</h3>
                    <ul className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        {linkedPhases.map((phase) => (
                            <li key={phase.id}>
                                <Link
                                    href={route("management.raids.create", { phase: phase.id })}
                                    className={linkClassName}
                                >
                                    Add a raid to {phase.name}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <p className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                <Link
                    href={route("management.phases.create", { game_version: gameVersion.id })}
                    className={linkClassName}
                >
                    Add a phase to {gameVersion.title}
                </Link>
                <Link href={route("management.phases.index")} className={linkClassName}>
                    Manage all phases
                </Link>
                <Link href={route("management.raids.index")} className={linkClassName}>
                    Manage all raids
                </Link>
            </p>
        </Relationships>
    );
}
