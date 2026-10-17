import Relationships from "@/Components/Datasets/Relationships";
import modelColor from "@/Helpers/ModelColor";

/**
 * The raids in a phase, read-only.
 */
export default function PhaseRaids({ raids }) {
    const list = Object.values(raids ?? {});

    return (
        <Relationships
            id="raids"
            title="Raids in this phase"
            description="Raids are linked to a phase when the raid is set up, so they can't be changed here."
        >
            {list.length === 0 ? (
                <p className="text-gray-300">No raids in this phase yet.</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {list.map((raid) => {
                        const swatch = modelColor(raid.color, ["bg"]);

                        return (
                            <li key={raid.id} className="flex items-center gap-3 text-white">
                                <span
                                    aria-hidden="true"
                                    className={`h-3 w-3 shrink-0 rounded-full border border-amber-600 ${swatch.className}`}
                                    style={swatch.style}
                                />
                                {raid.name}
                                <span className="text-sm text-gray-300">
                                    {raid.difficulty}
                                    {raid.max_players ? `, ${raid.max_players} players` : ""}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Relationships>
    );
}
