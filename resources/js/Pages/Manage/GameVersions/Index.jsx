import ConfirmationModal from "@/Components/ConfirmationModal";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import RecordCard, { usageSummary } from "@/Components/Datasets/RecordCard";
import SharedHeader from "@/Components/SharedHeader";
import { toHex } from "@/Helpers/ModelColor";
import useRecordDeletion from "@/Hooks/useRecordDeletion";
import Master from "@/Layouts/Master";

const USAGE_LABELS = [
    ["phases_count", "phase", "phases"],
    ["items_count", "item", "items"],
    ["characters_count", "character", "characters"],
    ["guild_ranks_count", "guild rank", "guild ranks"],
];

function GameVersionCard({ gameVersion, onDelete }) {
    const releaseDate = new Date(`${gameVersion.release_date}T00:00:00`).toLocaleDateString("en-GB", {
        day: "numeric",
        month: "long",
        year: "numeric",
    });

    return (
        <RecordCard
            title={gameVersion.title}
            subtitle={
                <>
                    Released <time dateTime={gameVersion.release_date}>{releaseDate}</time>
                </>
            }
            headerColor={gameVersion.color}
            headerImage={gameVersion.banner_url}
            details={[
                ["Realm", gameVersion.realm],
                ["Faction", gameVersion.faction],
                ["Colour", toHex(gameVersion.color)],
                ["Blizzard API namespace", gameVersion.blizzard.namespace],
                ["Warcraft Logs guild ID", gameVersion.warcraftlogs.guild],
                ["Warcraft Logs namespace", gameVersion.warcraftlogs.namespace.label],
            ]}
            usage={usageSummary(gameVersion, USAGE_LABELS)}
            links={gameVersion.links}
            onDelete={onDelete}
        />
    );
}

export default function Index({ versions: { data: versions }, routes }) {
    const deletion = useRecordDeletion();

    return (
        <Master title="Game versions">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Game versions" />

            <IndexLayout
                intro="The game versions the guild plays. Phases (and through them raids and bosses), items and characters are attached to one of these."
                addHref={routes.create}
                addLabel="Add game version"
                isEmpty={versions.length === 0}
                emptyIcon="gamepad"
                emptyMessage="No game versions yet. Add one to get started."
            >
                {versions.map((gameVersion) => (
                    <GameVersionCard
                        key={gameVersion.id}
                        gameVersion={gameVersion}
                        onDelete={() => deletion.request(gameVersion)}
                    />
                ))}
            </IndexLayout>

            <ConfirmationModal
                show={!!deletion.record}
                onClose={deletion.cancel}
                onConfirm={deletion.confirm}
                title={`Delete ${deletion.record?.title ?? "game version"}?`}
                confirmLabel="Delete"
                processingLabel="Deleting…"
                processing={deletion.deleting}
                variant="delete"
            >
                This removes the game version permanently. It can't be undone.
            </ConfirmationModal>
        </Master>
    );
}
