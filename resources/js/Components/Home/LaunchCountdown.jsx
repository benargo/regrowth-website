import { useEffect, useState } from "react";

const UNITS = [
    { key: "days", label: "Days" },
    { key: "hours", label: "Hours" },
    { key: "minutes", label: "Minutes" },
    { key: "seconds", label: "Seconds" },
];

/**
 * Split the milliseconds remaining into whole days/hours/minutes/seconds.
 * Clamps at zero so the countdown never runs negative after launch.
 */
function remainingUntil(targetIso) {
    const ms = new Date(targetIso).getTime() - Date.now();

    if (!Number.isFinite(ms) || ms <= 0) {
        return null;
    }

    const totalSeconds = Math.floor(ms / 1000);

    return {
        days: Math.floor(totalSeconds / 86400),
        hours: Math.floor((totalSeconds % 86400) / 3600),
        minutes: Math.floor((totalSeconds % 3600) / 60),
        seconds: totalSeconds % 60,
    };
}

/**
 * Countdown to the World of Warcraft: Forever launch, modelled on the band
 * on Blizzard's own Forever page.
 *
 * All arithmetic is client-side from a server-sent ISO target, so a cached
 * page can never render a stale countdown.
 */
export default function LaunchCountdown({ targetIso }) {
    // Computed synchronously for first paint so the numbers are correct
    // immediately rather than flashing zeros.
    const [remaining, setRemaining] = useState(() => remainingUntil(targetIso));

    useEffect(() => {
        setRemaining(remainingUntil(targetIso));

        const interval = setInterval(() => {
            const next = remainingUntil(targetIso);
            setRemaining(next);

            if (next === null) {
                clearInterval(interval);
            }
        }, 1000);

        return () => clearInterval(interval);
    }, [targetIso]);

    const launchDate = new Date(targetIso);
    const launchLabel = Number.isFinite(launchDate.getTime())
        ? launchDate.toLocaleDateString(undefined, { day: "numeric", month: "long" })
        : null;

    return (
        <div className="border-ink-600 from-ground-800 to-ground-900 border-b-8 bg-gradient-to-b">
            <div className="container mx-auto flex flex-col items-center gap-6 px-4 py-8 md:flex-row md:justify-between md:gap-10 md:py-10">
                <div className="flex items-center gap-4">
                    <img src="/images/icon_camelot.webp" alt="" aria-hidden="true" className="w-12 shrink-0 md:w-16" />
                    <p className="text-ink-400 text-center font-serif text-xl leading-snug md:text-left md:text-2xl">
                        {remaining === null ? (
                            <>Azeroth awaits — World of Warcraft: Forever is live!</>
                        ) : (
                            <>
                                World of Warcraft: Forever
                                <br className="hidden md:block" /> is coming{launchLabel ? ` ${launchLabel}` : ""}!
                            </>
                        )}
                    </p>
                </div>

                {remaining !== null && (
                    <div className="flex items-start gap-2 md:gap-4" role="timer" aria-live="off">
                        {UNITS.map((unit, index) => (
                            <div key={unit.key} className="flex items-start gap-2 md:gap-4">
                                <div className="flex min-w-[3.5rem] flex-col items-center md:min-w-[4.5rem]">
                                    <span className="text-ink-400 font-serif text-4xl leading-none tabular-nums md:text-5xl">
                                        {String(remaining[unit.key]).padStart(2, "0")}
                                    </span>
                                    <span className="text-ink-500 mt-2 text-[0.65rem] tracking-[0.15em] uppercase md:text-xs">
                                        {unit.label}
                                    </span>
                                </div>
                                {index < UNITS.length - 1 && (
                                    <span
                                        aria-hidden="true"
                                        className="text-ink-500/60 font-serif text-3xl leading-none md:text-4xl"
                                    >
                                        :
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* A single accessible summary, so screen readers get one sentence
                rather than four numbers updating every second. */}
            <p className="sr-only" aria-live="polite">
                {remaining === null
                    ? "World of Warcraft: Forever has launched."
                    : `${remaining.days} days, ${remaining.hours} hours, ${remaining.minutes} minutes until World of Warcraft: Forever launches.`}
            </p>
        </div>
    );
}
