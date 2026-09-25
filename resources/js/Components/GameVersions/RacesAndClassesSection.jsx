import { useForm } from "@inertiajs/react";
import { SaveButton, firstError } from "@/Components/FormControls";
import { AUTOSAVE_DELAY } from "@/Components/GameVersions/GameVersionForm";
import RecordChecklist from "@/Components/GameVersions/RecordChecklist";
import Relationships from "@/Datasets/Relationships";
import { useFormAutosave } from "@/Hooks/useAutosave";

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
    const form = useForm({
        playable_race_ids: races.selected_ids,
        playable_class_ids: classes.selected_ids,
    });

    const url = route("management.game-versions.update", gameVersion.id);
    const { schedule, containerProps } = useFormAutosave({
        form,
        url,
        saved: {
            playable_race_ids: races.selected_ids,
            playable_class_ids: classes.selected_ids,
        },
        key: "races-and-classes",
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
                    onChange={(ids) => {
                        form.setData("playable_race_ids", ids);
                        schedule();
                    }}
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
                    onChange={(ids) => {
                        form.setData("playable_class_ids", ids);
                        schedule();
                    }}
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
