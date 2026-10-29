import { useRef } from "react";
import { router } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import GameVersionIcon from "@/Components/GameVersions/GameVersionIcon";
import { FormRow, OptionSelect } from "@/Components/FormControls";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import UploadForm from "@/Components/GuildRosterManager/UploadForm";
import UploadProgressModal from "@/Components/GuildRosterManager/UploadProgressModal";
import UploadStatusDot from "@/Components/GuildRosterManager/UploadStatusDot";

const STATUS_DETAIL = {
    outdated: { tone: "text-yellow-400", text: "That's over a week ago, so upload a fresh export." },
    stale: { tone: "text-red-400", text: "The guild's member count has changed since, so upload a fresh export." },
    unknown: {
        tone: "text-gray-300",
        text: "Blizzard didn't return the guild's member count, so it couldn't be checked.",
    },
};

/**
 * One line summarising the selected version's latest upload, with the status
 * spelled out in words.
 */
function UploadStatusSummary({ gameVersion, status, lastUploadTimestamp }) {
    const detail = STATUS_DETAIL[status];

    return (
        <div className="text-md text-gray-400 mb-6 flex items-baseline gap-2">
            <UploadStatusDot status={status} />
            <span>
                {lastUploadTimestamp
                    ? `Last uploaded on ${lastUploadTimestamp}.`
                    : `Nothing has been uploaded for ${gameVersion.title} yet.`}
                {detail && <span className={`ml-1 ${detail.tone}`}>{detail.text}</span>}
            </span>
        </div>
    );
}

export default function Create({
    gameVersion: gameVersionResource,
    rosterVersions: { data: rosterVersions },
    lastUploadTimestamp,
    memberCount,
    uploadStatuses,
}) {
    const gameVersion = gameVersionResource?.data ?? null;
    // The modal sits outside the per-version subtree, and switching versions
    // preserves page state, so an upload's progress stays on screen.
    const progressModalRef = useRef(null);

    function selectGameVersion(slug) {
        router.reload({
            data: { game_version: slug },
            only: ["gameVersion", "lastUploadTimestamp", "memberCount"],
        });
    }

    return (
        <Master title="GRM Data Upload">
            <SharedHeader
                title="GRM Data Upload"
                backgroundClass="bg-officer-meeting"
                color={gameVersion?.color}
                image={gameVersion?.banner_url}
            />
            <ToolNav>
                <ToolNavLink href={route("management.dashboard")}>
                    <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                    Back to officers' dashboard
                </ToolNavLink>
            </ToolNav>
            <PageContainer>
                {gameVersion === null ? (
                    <p className="text-gray-400 text-lg">
                        No game version has a guild roster yet. Set one up before uploading GRM data.
                    </p>
                ) : (
                    <>
                        <ul className="mb-6 flex flex-col gap-2" aria-label="Upload status by game version">
                            {rosterVersions.map((version) => (
                                <li key={version.slug} className="flex items-center gap-2 text-gray-300">
                                    <GameVersionIcon gameVersion={version} />
                                    <span>{version.title}</span>
                                    <UploadStatusDot status={uploadStatuses?.[version.slug]} />
                                </li>
                            ))}
                        </ul>

                        <FormRow htmlFor="game_version" label="Game version" className="mb-6 max-w-xs">
                            <OptionSelect
                                name="game_version"
                                value={gameVersion.slug}
                                onChange={selectGameVersion}
                                options={rosterVersions.map((version) => ({ value: version.slug, label: version.title }))}
                            />
                        </FormRow>

                        <h2 className="mb-2 text-xl font-bold">
                            Upload GRM data for <span className="text-amber-500">{gameVersion.title}</span>
                        </h2>
                        <UploadStatusSummary
                            gameVersion={gameVersion}
                            status={uploadStatuses?.[gameVersion.slug]}
                            lastUploadTimestamp={lastUploadTimestamp}
                        />

                        <UploadForm
                            key={gameVersion.slug}
                            gameVersion={gameVersion}
                            memberCount={memberCount}
                            onUploaded={() => progressModalRef.current?.start()}
                        />
                    </>
                )}
            </PageContainer>

            <UploadProgressModal ref={progressModalRef} />
        </Master>
    );
}
