import { useEffect, useState } from "react";
import { formatDateTime } from "@/Helpers/serverTime";
import cn from "@/Helpers/cn";

const RELATIVE_FORMAT = new Intl.RelativeTimeFormat("en-GB", { numeric: "always" });

const UNITS = [
    { unit: "day", seconds: 86400 },
    { unit: "hour", seconds: 3600 },
    { unit: "minute", seconds: 60 },
];

/**
 * The time since `isoString` in words, such as "12 seconds ago" or
 * "3 minutes ago", or "just now" within five seconds.
 */
function timeSince(isoString, now) {
    const elapsedSeconds = Math.max(0, Math.floor((now - new Date(isoString).getTime()) / 1000));

    if (elapsedSeconds < 5) {
        return "just now";
    }

    const match = UNITS.find(({ seconds }) => elapsedSeconds >= seconds);

    return match
        ? RELATIVE_FORMAT.format(-Math.floor(elapsedSeconds / match.seconds), match.unit)
        : RELATIVE_FORMAT.format(-elapsedSeconds, "second");
}

/**
 * A "Checked 12 seconds ago" indicator for the ISO timestamp `at`, re-rendered
 * every second, with the exact time in its tooltip. Shows "Not checked yet"
 * when `at` is null.
 */
export default function CheckedAgo({ at, className = "" }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!at) {
            return;
        }

        setNow(Date.now());
        const interval = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(interval);
    }, [at]);

    return (
        <p className={cn("text-camel-500 flex items-center gap-2 text-sm", className)}>
            <span
                aria-hidden="true"
                className={cn(
                    "size-2 rounded-full",
                    at ? "bg-forever-100 shadow-[0_0_6px_var(--color-forever-100)]" : "bg-camel-800",
                )}
            />
            {at ? (
                <time dateTime={at} title={formatDateTime(at)}>
                    Checked {timeSince(at, now)}
                </time>
            ) : (
                <span>Not checked yet</span>
            )}
        </p>
    );
}
