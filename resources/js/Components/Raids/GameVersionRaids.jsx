import RaidCard from "@/Components/Raids/RaidCard";
import modelColor from "@/Helpers/ModelColor";

/**
 * One game version's raids on the Index page. A group with no game version
 * holds the raids whose phase isn't linked to one. onDelete(raid, warning)
 * asks to delete a raid.
 */
export default function GameVersionRaids({ group, onDelete }) {
    const gameVersion = group.game_version?.data ?? null;
    const raids = group.raids.data;
    const title = gameVersion?.title ?? "No game version";
    const headingId = `raids-${gameVersion?.id ?? "none"}`;
    const rule = modelColor(gameVersion?.color, ["bg"]);

    return (
        <section aria-labelledby={headingId} className="flex flex-col gap-6">
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

            {raids.length === 0 ? (
                <p className="text-gray-300">No raids in {title} yet.</p>
            ) : (
                <ul className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {raids.map((raid) => (
                        <li key={raid.id}>
                            <RaidCard raid={raid} onDelete={(warning) => onDelete(raid, warning)} />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
