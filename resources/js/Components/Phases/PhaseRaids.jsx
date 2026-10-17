import { FormPageSection } from "@/Components/FormPage";
import modelColor from "@/Helpers/ModelColor";

/**
 * The raids in a phase, read-only. Raids are linked to phases from the raid side.
 */
export default function PhaseRaids({ raids }) {
    const list = Object.values(raids ?? {});

    return (
        <FormPageSection id="raids">
            <section aria-labelledby="raids-heading" className="flex flex-col gap-4 border-t border-amber-600/40 pt-8">
                <h2 id="raids-heading" className="font-serif text-2xl text-white">
                    Raids in this phase
                </h2>
                <p className="max-w-prose text-sm text-gray-300">
                    Raids are linked to a phase when the raid is set up, so they can't be changed here.
                </p>
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
            </section>
        </FormPageSection>
    );
}
