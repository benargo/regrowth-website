import { formatDateTime } from "@/Helpers/serverTime";

/**
 * A phase's start date in server time, with the viewer's local time when it
 * differs, or "Not scheduled".
 */
export default function PhaseStartDate({ startDate }) {
    if (!startDate) {
        return "Not scheduled";
    }

    const serverTime = formatDateTime(startDate);
    const localTime = formatDateTime(startDate, undefined);

    return (
        <span className="flex flex-col">
            <time dateTime={startDate}>{serverTime} (server)</time>
            {localTime !== serverTime && <span className="text-sm text-gray-300">{localTime} your time</span>}
        </span>
    );
}
