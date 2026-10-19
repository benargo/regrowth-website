import GameVersionBosses from "@/Components/Bosses/GameVersionBosses";
import ConfirmationModal from "@/Components/ConfirmationModal";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import SharedHeader from "@/Components/SharedHeader";
import useRecordDeletion from "@/Hooks/useRecordDeletion";
import Master from "@/Layouts/Master";

export default function Index({ groups, routes }) {
    const deletion = useRecordDeletion();
    const hasRaids = groups.some((group) => group.raids.length > 0);

    return (
        <Master title="Bosses">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Bosses" />

            <IndexLayout
                intro="Each raid's bosses in encounter order, with their strategies. Change the order from the raid's Bosses step."
                addHref={hasRaids ? routes.create : null}
                addLabel="Add boss"
                layout="stack"
                isEmpty={!hasRaids}
                emptyIcon="skull"
                emptyMessage="No raids yet. Add a raid before adding its bosses."
            >
                {groups.map((group) => (
                    <GameVersionBosses
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
                title={`Delete ${deletion.record?.name ?? "boss"}?`}
                confirmLabel="Delete"
                processingLabel="Deleting…"
                processing={deletion.deleting}
                variant="delete"
            >
                {deletion.deleteWarning && <p className="mb-2 text-amber-200">{deletion.deleteWarning}</p>}
                <p>This removes the boss and its strategy permanently. It can't be undone.</p>
            </ConfirmationModal>
        </Master>
    );
}
