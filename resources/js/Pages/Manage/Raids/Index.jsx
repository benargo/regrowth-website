import ConfirmationModal from "@/Components/ConfirmationModal";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import GameVersionRaids from "@/Components/Raids/GameVersionRaids";
import SharedHeader from "@/Components/SharedHeader";
import useRecordDeletion from "@/Hooks/useRecordDeletion";
import Master from "@/Layouts/Master";

export default function Index({ groups, routes }) {
    const deletion = useRecordDeletion();

    return (
        <Master title="Raids">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Raids" />

            <IndexLayout
                intro="Each game version's raids, in the order their phases arrive. A raid's bosses are ordered from its Edit page."
                addHref={routes.create}
                addLabel="Add raid"
                layout="stack"
                isEmpty={groups.length === 0}
                emptyIcon="dungeon"
                emptyMessage="No game versions or raids yet. Add a raid to get started."
            >
                {groups.map((group) => (
                    <GameVersionRaids
                        key={group.game_version?.data.id ?? "none"}
                        group={group}
                        onDelete={deletion.request}
                    />
                ))}
            </IndexLayout>

            <ConfirmationModal
                show={!!deletion.record}
                onClose={deletion.cancel}
                onConfirm={deletion.confirm}
                title={`Delete ${deletion.record?.name ?? "raid"}?`}
                confirmLabel="Delete"
                processingLabel="Deleting…"
                processing={deletion.deleting}
                variant="delete"
            >
                {deletion.deleteWarning && <p className="mb-2 text-amber-200">{deletion.deleteWarning}</p>}
                <p>This removes the raid permanently. It can't be undone.</p>
            </ConfirmationModal>
        </Master>
    );
}
