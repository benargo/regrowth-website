import ConfirmationModal from "@/Components/ConfirmationModal";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import GameVersionSelector from "@/Components/GameVersions/GameVersionSelector";
import GameVersionPhases from "@/Components/Phases/GameVersionPhases";
import SharedHeader from "@/Components/SharedHeader";
import { AutosaveProvider } from "@/Hooks/useAutosave";
import useRecordDeletion from "@/Hooks/useRecordDeletion";
import Master from "@/Layouts/Master";

export default function Index({ gameVersion, gameVersions, groups, routes }) {
    const deletion = useRecordDeletion();

    return (
        <Master title="Phases">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Phases" />

            <AutosaveProvider>
                <IndexLayout
                    intro="A game version's content phases. Phases with a start date follow it; put the rest in the order you expect them to arrive."
                    addHref={routes.create}
                    addLabel="Add phase"
                    layout="stack"
                    isEmpty={groups.length === 0}
                    emptyIcon="hourglass-start"
                    emptyMessage="No game versions or phases yet. Add a phase to get started."
                >
                    <div>
                        <GameVersionSelector gameVersions={gameVersions.data} currentGameVersion={gameVersion?.data} />
                        <div className="flex flex-col gap-12">
                            {groups.map((group) => (
                                <GameVersionPhases
                                    key={group.game_version?.data.id ?? "none"}
                                    group={group}
                                    reorderUrl={routes.reorder}
                                    onDelete={deletion.request}
                                />
                            ))}
                        </div>
                    </div>
                </IndexLayout>
            </AutosaveProvider>

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
