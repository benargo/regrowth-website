import Tooltip from "@/Components/Tooltip";

export const UPLOAD_STATUS_LABELS = {
    current: "Up to date",
    outdated: "Over a week old",
    stale: "Member count has changed",
    missing: "Nothing uploaded yet",
    unknown: "Couldn't check with Blizzard",
};

const DOT_CLASSES = {
    current: "bg-green-500",
    outdated: "bg-yellow-500",
    stale: "bg-red-500",
    missing: "border-2 border-gray-400",
    unknown: "bg-gray-500",
};

/**
 * A small status light for a game version's latest GRM upload. Pulses while the
 * deferred `uploadStatuses` prop is still loading (status undefined). With `isDecorative`, the
 * dot is hidden from assistive technology and has no tooltip, for use beside a visible label.
 */
export default function UploadStatusDot({ status, isDecorative = false }) {
    if (status === undefined) {
        return (
            <span
                aria-hidden="true"
                className="bg-brown-700 inline-block size-2.5 rounded-full motion-safe:animate-pulse"
            />
        );
    }

    if (isDecorative) {
        return <span aria-hidden="true" className={`inline-block size-2.5 rounded-full ${DOT_CLASSES[status]}`} />;
    }

    return (
        <Tooltip body={UPLOAD_STATUS_LABELS[status]} className="leading-none">
            <span
                role="img"
                aria-label={UPLOAD_STATUS_LABELS[status]}
                className={`inline-block size-2.5 rounded-full ${DOT_CLASSES[status]}`}
            />
        </Tooltip>
    );
}
