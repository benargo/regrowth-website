import Section from "@/Components/Home/Section";
import DisplayHeading from "@/Components/Home/DisplayHeading";
import OfficerLineup from "@/Components/Home/OfficerLineup";

/**
 * The officer team: an intro, then the council and the officers as two
 * lineups, each on its own ledge. The painted backdrop darkens towards the
 * bottom of the band.
 */
export default function OfficerTeam({ officers = [] }) {
    if (officers.length === 0) {
        return null;
    }

    const council = officers.filter((officer) => officer.tier === "council");
    const officerTier = officers.filter((officer) => officer.tier === "officer");

    return (
        <Section
            tone="parchment"
            edge="bottom"
            edgeTone="mid"
            className="before:via-forever-900/20 before:to-forever-900/60 before:pointer-events-none before:absolute before:inset-0 before:bg-linear-to-b before:from-transparent"
        >
            <div className="mb-12 md:mb-16">
                <DisplayHeading level={2} eyebrow="Who leads us" className="mb-4 text-center">
                    Meet the Officers
                </DisplayHeading>

                <p className="text-camel-200 mx-auto max-w-2xl text-center text-pretty">
                    Regrowth is steered by a council and a team of officers who organise the raids, settle the loot and
                    keep the guild running.
                </p>
            </div>

            <OfficerLineup title="The Council" officers={council} tier="council" />

            <OfficerLineup title="Officers" officers={officerTier} tier="officer" className="mt-16 md:mt-24" />
        </Section>
    );
}
