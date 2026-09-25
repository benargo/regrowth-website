import { useId, useState } from "react";
import { Button } from "@headlessui/react";
import { Link, router } from "@inertiajs/react";
import ConfirmationModal from "@/Components/ConfirmationModal";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

const USAGE_LABELS = [
    ["phases_count", "phase", "phases"],
    ["zones_count", "zone", "zones"],
    ["items_count", "item", "items"],
    ["characters_count", "character", "characters"],
];

const focusRing = "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink-400";

function usageSummary(gameVersion) {
    return USAGE_LABELS.filter(([key]) => gameVersion[key] > 0).map(
        ([key, singular, plural]) => `${gameVersion[key]} ${gameVersion[key] === 1 ? singular : plural}`,
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-secondary-300 text-sm">{label}</dt>
            <dd className="text-white">{value ?? "Not set"}</dd>
        </div>
    );
}

function GameVersionCard({ gameVersion, onDeleteClick }) {
    const usageId = useId();
    const usage = usageSummary(gameVersion);
    const inUse = usage.length > 0;
    const releaseDate = new Date(`${gameVersion.release_date}T00:00:00`).toLocaleDateString("en-GB", {
        day: "numeric",
        month: "long",
        year: "numeric",
    });

    return (
        <article className="border-ink-600/40 bg-ground-800/60 overflow-hidden rounded border">
            <header className={`${gameVersion.banner_class} bg-cover bg-center`}>
                <div className="flex flex-col gap-1 bg-linear-to-r from-black/85 to-black/40 px-4 py-5">
                    <h2 className="font-serif text-2xl text-white">{gameVersion.title}</h2>
                    <p className="text-secondary-300 text-sm">
                        Released <time dateTime={gameVersion.release_date}>{releaseDate}</time>
                    </p>
                </div>
            </header>

            <div className="flex flex-col gap-4 p-4">
                <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Detail label="Realm" value={gameVersion.realm} />
                    <Detail label="Faction" value={gameVersion.faction} />
                    <Detail label="Theme" value={gameVersion.theme} />
                    <Detail label="Blizzard API namespace" value={gameVersion.blizzard_namespace} />
                    <Detail label="Warcraft Logs guild ID" value={gameVersion.warcraftlogs_guild} />
                    <Detail label="Warcraft Logs expansion ID" value={gameVersion.warcraftlogs_expansion} />
                </dl>

                <p id={usageId} className="text-secondary-300 text-sm">
                    {inUse
                        ? `Used by ${usage.join(", ")}. Remove or reassign these before deleting.`
                        : "Not used by any records yet."}
                </p>

                <div className="flex flex-wrap gap-2">
                    <Link
                        href={route("management.game-versions.edit", gameVersion.id)}
                        className={`border-ink-500 hover:bg-ink-600/30 inline-flex items-center gap-1.5 rounded border px-3 py-1.5 text-sm text-white ${focusRing}`}
                    >
                        <Icon icon="edit" style="light" />
                        Edit<span className="sr-only"> {gameVersion.title}</span>
                    </Link>
                    <Button
                        disabled={inUse}
                        aria-describedby={usageId}
                        onClick={() => onDeleteClick(gameVersion)}
                        className="data-disabled:border-secondary-600 data-disabled:text-secondary-400 inline-flex items-center gap-1.5 rounded border border-red-400 px-3 py-1.5 text-sm text-red-300 data-disabled:cursor-not-allowed data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-red-400 data-hover:bg-red-600/20"
                    >
                        <Icon icon="trash" style="light" />
                        Delete<span className="sr-only"> {gameVersion.title}</span>
                    </Button>
                </div>
            </div>
        </article>
    );
}

export default function Index({ gameVersions }) {
    const [gameVersionToDelete, setGameVersionToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    function handleDelete() {
        setDeleting(true);
        router.delete(route("management.game-versions.destroy", gameVersionToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setGameVersionToDelete(null);
            },
        });
    }

    return (
        <Master title="Game versions">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Game versions" />
            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={route("management.dashboard")} className={focusRing}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to officers' dashboard
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-secondary-300">
                        The game versions the guild plays. Phases (and through them raids and bosses), items and
                        characters are attached to one of these.
                    </p>
                    <Link
                        href={route("management.game-versions.create")}
                        className={`bg-ink-800 hover:bg-ink-900 inline-flex items-center justify-center gap-2 rounded px-4 py-2 text-sm font-semibold text-white ${focusRing}`}
                    >
                        <Icon icon="plus" style="light" />
                        Add game version
                    </Link>
                </div>

                {gameVersions.length === 0 ? (
                    <EmptyState icon="gamepad" message="No game versions yet. Add one to get started." />
                ) : (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {gameVersions.map((gameVersion) => (
                            <GameVersionCard
                                key={gameVersion.id}
                                gameVersion={gameVersion}
                                onDeleteClick={setGameVersionToDelete}
                            />
                        ))}
                    </div>
                )}
            </PageContainer>

            <ConfirmationModal
                show={!!gameVersionToDelete}
                onClose={() => setGameVersionToDelete(null)}
                onConfirm={handleDelete}
                title={`Delete ${gameVersionToDelete?.title ?? "game version"}?`}
                confirmLabel="Delete"
                processingLabel="Deleting…"
                processing={deleting}
                variant="delete"
            >
                This removes the game version permanently. It can't be undone.
            </ConfirmationModal>
        </Master>
    );
}
