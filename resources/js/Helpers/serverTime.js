/** The game servers' time zone, in which officers enter dates. */
export const SERVER_TIME_ZONE = "Europe/Paris";

/**
 * The ISO timestamp as a datetime-local input value in server time, such as
 * "2026-10-23T19:00", or "" when there is none.
 */
export function toServerDateTimeLocal(isoString) {
    if (!isoString) {
        return "";
    }

    return new Intl.DateTimeFormat("sv-SE", {
        timeZone: SERVER_TIME_ZONE,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    })
        .format(new Date(isoString))
        .replace(" ", "T");
}

/**
 * The ISO timestamp for reading, such as "23 Oct 2026, 19:00 CEST", in server
 * time, or in the viewer's own time zone when `timeZone` is undefined.
 */
export function formatDateTime(isoString, timeZone = SERVER_TIME_ZONE) {
    return new Date(isoString).toLocaleString("en-GB", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        timeZone,
        timeZoneName: "short",
    });
}

/**
 * Whether the viewer's time zone shows the timestamp differently from server time.
 */
export function differsFromServerTime(isoString) {
    return formatDateTime(isoString) !== formatDateTime(isoString, undefined);
}
