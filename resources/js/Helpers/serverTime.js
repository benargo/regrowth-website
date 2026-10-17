/** The game servers' time zone, in which officers enter dates. */
export const SERVER_TIME_ZONE = "Europe/Paris";

const DATE_TIME_LOCAL_FORMAT = new Intl.DateTimeFormat("sv-SE", {
    timeZone: SERVER_TIME_ZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
});

const READABLE_FORMATS = new Map();

/**
 * The ISO timestamp as a datetime-local input value in server time, such as
 * "2026-10-23T19:00", or "" when there is none.
 */
export function toServerDateTimeLocal(isoString) {
    if (!isoString) {
        return "";
    }

    return DATE_TIME_LOCAL_FORMAT.format(new Date(isoString)).replace(" ", "T");
}

/**
 * The ISO timestamp for reading, such as "23 Oct 2026, 19:00 CEST", in server
 * time, or in the viewer's own time zone when `timeZone` is null. Each time
 * zone's formatter is built once.
 */
export function formatDateTime(isoString, timeZone = SERVER_TIME_ZONE) {
    if (!READABLE_FORMATS.has(timeZone)) {
        READABLE_FORMATS.set(
            timeZone,
            new Intl.DateTimeFormat("en-GB", {
                day: "numeric",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                timeZone: timeZone ?? undefined,
                timeZoneName: "short",
            }),
        );
    }

    return READABLE_FORMATS.get(timeZone).format(new Date(isoString));
}
