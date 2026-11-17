import Card from "@/Components/Card";
import GameVersionIcon from "@/Components/GameVersions/GameVersionIcon";
import UploadStatusDot, { UPLOAD_STATUS_LABELS } from "@/Components/GuildRosterManager/UploadStatusDot";
import { OUTCOME_LABELS, OUTCOME_TONES, SOURCE_LABELS } from "@/Components/GuildRosterManager/syncLabels";
import { formatDateTime } from "@/Helpers/serverTime";

/**
 * A roster's freshness: its upload status, and its newest accepted upload with who sent it and from where.
 */
export default function RosterFreshnessCard({ roster }) {
    const { gameVersion, status, lastUpload } = roster;

    return (
        <Card
            title={
                <span className="flex items-center gap-2">
                    <GameVersionIcon gameVersion={gameVersion} />
                    {gameVersion.title}
                </span>
            }
        >
            <div className="flex items-center gap-2 text-gray-200">
                <UploadStatusDot status={status} isDecorative />
                {UPLOAD_STATUS_LABELS[status]}
            </div>
            <p className="mt-2 text-sm break-words text-gray-400">
                {lastUpload ? (
                    <>
                        {SOURCE_LABELS[lastUpload.source]} on {formatDateTime(lastUpload.startedAt, null)}
                        {lastUpload.uploader && <> by {lastUpload.uploader.name}</>}
                        {lastUpload.device && <> from {lastUpload.device.name}</>}.{" "}
                        <span className={OUTCOME_TONES[lastUpload.outcome]}>{OUTCOME_LABELS[lastUpload.outcome]}.</span>
                    </>
                ) : (
                    "Nothing has been uploaded or synced yet."
                )}
            </p>
        </Card>
    );
}
