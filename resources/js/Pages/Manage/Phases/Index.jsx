import ConfirmationModal from "@/Components/ConfirmationModal";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import GameVersionPhases from "@/Components/Phases/GameVersionPhases";
import SharedHeader from "@/Components/SharedHeader";
import useRecordDeletion from "@/Hooks/useRecordDeletion";
import Master from "@/Layouts/Master";

export default function Index({ groups, routes }) {
    const deletion = useRecordDeletion();

    return (
        <Master title="Phases">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Phases" />

            <IndexLayout
                intro="Each game version's content phases. Phases with a start date follow it; put the rest in the order you expect them to arrive."
                addHref={routes.create}
                addLabel="Add phase"
                layout="stack"
                isEmpty={groups.length === 0}
                emptyIcon="hourglass-start"
                emptyMessage="No game versions or phases yet. Add a phase to get started."
            >
                {groups.map((group) => (
                    <GameVersionPhases
                        key={group.game_version?.data.id ?? "none"}
                        group={group}
                        reorderUrl={routes.reorder}
                        onDelete={deletion.request}
                    />
                ))}
            </IndexLayout>

            <ConfirmationModal
                show={!!deletion.record}
                onClose={deletion.cancel}
                onConfirm={deletion.confirm}
                title={`Delete ${deletion.record?.name ?? "phase"}?`}
                confirmLabel="Delete"
                processingLabel="Deleting…"
                processing={deletion.deleting}
                variant="delete"
            >
                This removes the phase permanently. It can't be undone.
            </ConfirmationModal>
        </Master>
    );
}
