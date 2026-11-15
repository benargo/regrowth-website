import Card from "@/Components/Card";
import Icon from "@/Components/FontAwesome/Icon";
import GameVersionIcon from "@/Components/GameVersions/GameVersionIcon";
import UploadStatusDot, { UPLOAD_STATUS_LABELS } from "@/Components/GuildRosterManager/UploadStatusDot";
import { OUTCOME_LABELS, OUTCOME_TONES, SOURCE_LABELS } from "@/Components/GuildRosterManager/syncLabels";
import { formatDateTime } from "@/Helpers/serverTime";

/**
 * A roster's freshness: its upload status, its newest accepted upload with who sent it and from where, and
 * a warning listing each rank whose name in game no longer matches the site's.
 */
export default function RosterFreshnessCard({ roster }) {
    const { gameVersion, status, lastUpload, rankNameDifferences } = roster;

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

            {rankNameDifferences.length > 0 && (
                <div className="mt-4 rounded border-l-4 border-yellow-500 bg-yellow-500/10 p-3" role="note">
                    <p className="flex gap-2 text-sm text-yellow-100">
                        <Icon
                            icon="exclamation-triangle"
                            style="solid"
                            className="mt-0.5 text-yellow-400"
                            aria-hidden="true"
                        />
                        <span>
                            The game's rank names no longer match the site's ranks. Review the site's ranks before
                            relying on them.
                        </span>
                    </p>
                    <table className="mt-3 w-full text-left text-sm">
                        <caption className="sr-only">Rank names that differ, by position</caption>
                        <thead className="text-gray-400">
                            <tr>
                                <th scope="col" className="py-1 pr-3 font-medium">
                                    Position
                                </th>
                                <th scope="col" className="py-1 pr-3 font-medium">
                                    In game
                                </th>
                                <th scope="col" className="py-1 font-medium">
                                    On the site
                                </th>
                            </tr>
                        </thead>
                        <tbody className="text-gray-200">
                            {rankNameDifferences.map((difference) => (
                                <tr key={difference.sortOrder}>
                                    <td className="py-1 pr-3 tabular-nums">{difference.sortOrder + 1}</td>
                                    <td className="py-1 pr-3">
                                        {difference.gameName ?? <span className="text-gray-400 italic">None</span>}
                                    </td>
                                    <td className="py-1">
                                        {difference.siteName ?? <span className="text-gray-400 italic">None</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}
