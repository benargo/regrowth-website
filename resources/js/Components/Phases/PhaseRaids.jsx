import { Link } from "@inertiajs/react";
import { focusRing } from "@/Components/Datasets/RecordCard";
import Relationships from "@/Components/Datasets/Relationships";
import Icon from "@/Components/FontAwesome/Icon";
import { linkClassName } from "@/Components/FormControls";
import modelColor from "@/Helpers/ModelColor";

/**
 * The raids in a phase, each linking to its Edit page, and a link to add a
 * raid to the phase.
 */
export default function PhaseRaids({ phase }) {
    const list = Object.values(phase.raids ?? {});

    return (
        <Relationships
            id="raids"
            title="Raids in this phase"
            description="A raid's phase is chosen on the raid's own page."
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
                                <Link
                                    href={route("management.raids.edit", raid.id)}
                                    className={`rounded underline hover:text-amber-200 ${focusRing}`}
                                >
                                    {raid.name}
                                </Link>
                                <span className="text-sm text-gray-300">
                                    {raid.difficulty}
                                    {raid.max_players ? `, ${raid.max_players} players` : ""}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}

            <p className="text-sm">
                <Link href={route("management.raids.create", { phase: phase.id })} className={linkClassName}>
                    <Icon icon="plus" className="mr-1" />
                    Add raid<span className="sr-only"> to {phase.name}</span>
                </Link>
            </p>
        </Relationships>
    );
}
