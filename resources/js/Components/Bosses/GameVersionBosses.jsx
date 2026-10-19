import { Link } from "@inertiajs/react";
import BossCard from "@/Components/Bosses/BossCard";
import { focusRing } from "@/Components/Datasets/RecordCard";
import modelColor from "@/Helpers/ModelColor";

/**
 * One raid's bosses in encounter order, with a link to add another.
 */
function RaidBosses({ raid, bosses, onDelete }) {
    const headingId = `raid-${raid.id}-bosses`;

    return (
        <section aria-labelledby={headingId} className="flex flex-col gap-4">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                <h3 id={headingId} className="font-serif text-2xl text-white">
                    {raid.name}
                </h3>
                <Link
                    href={route("management.bosses.create", { raid: raid.id })}
                    className={`text-sm text-gray-300 underline hover:text-white ${focusRing}`}
                >
                    Add a boss<span className="sr-only"> to {raid.name}</span>
                </Link>
            </div>

            {bosses.length === 0 ? (
                <p className="text-gray-300">No bosses in {raid.name} yet.</p>
            ) : (
                <ol aria-label={`${raid.name} bosses in encounter order`} className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {bosses.map((boss) => (
                        <li key={boss.id}>
                            <BossCard boss={boss} raidName={raid.name} onDelete={onDelete} />
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}

/**
 * One game version's raids and their bosses on the Index page. A group with
 * no game version holds the raids whose phase isn't linked to one.
 */
export default function GameVersionBosses({ group, onDelete }) {
    const gameVersion = group.game_version?.data ?? null;
    const title = gameVersion?.title ?? "No game version";
    const headingId = `bosses-${gameVersion?.id ?? "none"}`;
    const rule = modelColor(gameVersion?.color, ["bg"]);

    return (
        <section aria-labelledby={headingId} className="flex flex-col gap-8">
            <header className="flex flex-col gap-2">
                <h2 id={headingId} className="font-serif text-3xl text-white">
                    {title}
                </h2>
                <div
                    aria-hidden="true"
                    className={`h-0.5 w-full ${rule.className || "bg-amber-600/60"}`}
                    style={rule.style}
                />
            </header>

            {group.raids.length === 0 && <p className="text-gray-300">No raids in this game version yet.</p>}

            {group.raids.map(({ raid: { data: raid }, bosses: { data: bosses } }) => (
                <RaidBosses key={raid.id} raid={raid} bosses={bosses} onDelete={onDelete} />
            ))}
        </section>
    );
}
