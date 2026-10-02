import { useRef } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import TabNav from "@/Components/TabNav";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import UploadForm from "@/Components/GuildRosterManager/UploadForm";
import UploadProgressModal from "@/Components/GuildRosterManager/UploadProgressModal";
import UploadStatusDot from "@/Components/GuildRosterManager/UploadStatusDot";

const STATUS_DETAIL = {
    outdated: { tone: "text-yellow-400", text: "That's over a week ago, so upload a fresh export." },
    stale: { tone: "text-red-400", text: "The guild's member count has changed since, so upload a fresh export." },
    unknown: {
        tone: "text-secondary-300",
        text: "Blizzard didn't return the guild's member count, so it couldn't be checked.",
    },
};

/**
 * One line summarising the selected version's latest upload, with the status
 * spelled out so the tab dots are never the only signal.
 */
function UploadStatusSummary({ gameVersion, status, lastUploadTimestamp }) {
    const detail = STATUS_DETAIL[status];

    return (
        <div className="text-md text-secondary-400 mb-6 flex items-baseline gap-2">
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

export default function Create({ gameVersion, gameVersions, lastUploadTimestamp, memberCount, uploadStatuses }) {
    // The modal sits outside the per-version subtree, and the tabs preserve page
    // state, so switching tabs while an upload is processing keeps its progress on screen.
    const progressModalRef = useRef(null);

    return (
        <Master title="GRM Data Upload">
            <SharedHeader title="GRM Data Upload" backgroundClass={gameVersion?.banner_class ?? "bg-officer-meeting"} />
            <ToolNav>
                <ToolNavLink href={route("management.dashboard")}>
                    <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                    Back to officers' dashboard
                </ToolNavLink>
            </ToolNav>
            <PageContainer>
                {gameVersion === null ? (
                    <p className="text-secondary-400 text-lg">
                        No game version has a guild roster yet. Set one up before uploading GRM data.
                    </p>
                ) : (
                    <>
                        {gameVersions.length > 1 && (
                            <TabNav
                                currentTab={gameVersion.slug}
                                preserveState
                                tabs={gameVersions.map((version) => ({
                                    name: version.slug,
                                    label: version.title,
                                    href: route("management.grm.create", { game_version: version.slug }),
                                    indicator: <UploadStatusDot status={uploadStatuses?.[version.slug]} />,
                                }))}
                            />
                        )}

                        <h2 className="mb-2 text-xl font-bold">
                            Upload GRM data for <span className="text-heading">{gameVersion.title}</span>
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
