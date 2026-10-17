import { differsFromServerTime, formatDateTime } from "@/Helpers/serverTime";

/**
 * A phase's start date in server time, with the viewer's local time when it
 * differs, or "Not scheduled".
 */
export default function PhaseStartDate({ startDate }) {
    if (!startDate) {
        return "Not scheduled";
    }

    return (
        <span className="flex flex-col">
            <time dateTime={startDate}>{formatDateTime(startDate)} (server)</time>
            {differsFromServerTime(startDate) && (
                <span className="text-sm text-gray-300">{formatDateTime(startDate, undefined)} your time</span>
            )}
        </span>
    );
}
