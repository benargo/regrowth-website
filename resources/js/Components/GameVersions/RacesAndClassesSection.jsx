import RecordChecklist from "@/Components/Datasets/RecordChecklist";
import Relationships from "@/Components/Datasets/Relationships";
import { SaveButton, firstError } from "@/Components/FormControls";
import useRelationshipForm from "@/Hooks/useRelationshipForm";

function ClassLabel({ playableClass }) {
    return (
        <span className="flex items-center gap-2">
            {playableClass.icon_url && <img src={playableClass.icon_url} alt="" className="h-6 w-6 rounded" />}
            {playableClass.name}
        </span>
    );
}

export default function RacesAndClassesSection({
    gameVersion,
    relationships,
    submitLabel = "Save races and classes",
    onSaved,
    autosave = false,
}) {
    const { playable_races: races, playable_classes: classes } = relationships;
    const { form, setIds, handleSubmit, containerProps } = useRelationshipForm({
        url: route("management.game-versions.update", gameVersion.id),
        key: "races-and-classes",
        selected: {
            playable_race_ids: races.selected_ids,
            playable_class_ids: classes.selected_ids,
        },
        autosave,
        onSaved,
    });

    return (
        <Relationships
            id="races-and-classes"
            title="Races and classes"
            description="Tick every race and class players can choose in this version."
        >
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8" {...containerProps}>
                <RecordChecklist
                    legend="Races"
                    name="playable_race_ids"
                    options={races.options}
                    selectedIds={form.data.playable_race_ids}
                    onChange={(ids) => setIds("playable_race_ids", ids)}
                    groupBy={(race) => race.faction}
                    emptyMessage="No races exist yet."
                    selectAll
                    error={firstError(form.errors, "playable_race_ids")}
                />
                <RecordChecklist
                    legend="Classes"
                    name="playable_class_ids"
                    options={classes.options}
                    selectedIds={form.data.playable_class_ids}
                    onChange={(ids) => setIds("playable_class_ids", ids)}
                    renderLabel={(playableClass) => <ClassLabel playableClass={playableClass} />}
                    emptyMessage="No classes exist yet."
                    selectAll
                    error={firstError(form.errors, "playable_class_ids")}
                />
                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>
        </Relationships>
    );
}
