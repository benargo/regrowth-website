import { Link } from "@inertiajs/react";
import Section from "@/Components/Home/Section";
import DisplayHeading from "@/Components/Home/DisplayHeading";
import Timeline, { TimelineEntry } from "@/Components/Timeline/Timeline";

const MILESTONES = [
    {
        year: "2021",
        title: "Founded",
        description: "Formed on Mirage Raceway from the ashes of Low Level Fight Club.",
    },
    {
        year: "2021",
        title: "Classic Raiding",
        description:
            "Raided through The Burning Crusade and into Wrath of the Lich King, then stayed together as a social home.",
    },
    {
        year: "2024",
        title: "Anniversary",
        description:
            "Reborn on the Anniversary servers to take on 40-man content again, then raided through all of The Burning Crusade until October 2026.",
    },
    {
        year: "2026",
        title: "WoW Forever",
        description: "Regrowth begins again on the Normal ruleset as Horde.",
    },
];

/**
 * A summary of the guild's history as a timeline of milestones, linking to
 * the full history on the About page.
 */
export default function HistoryTimeline() {
    return (
        <Section tone="deep" edge="bottom" edgeTone="mid">
            <DisplayHeading level={2} eyebrow="Since 2021" className="mb-12 text-center">
                Our Story So Far
            </DisplayHeading>

            <Timeline palette="camel" isCompact className="mx-auto max-w-md lg:grid lg:max-w-none lg:grid-cols-4">
                {MILESTONES.map((milestone, index) => (
                    <TimelineEntry
                        key={milestone.title}
                        date={milestone.year}
                        dateTime={milestone.year}
                        title={milestone.title}
                        isLatest={index === MILESTONES.length - 1}
                    >
                        <p className="text-camel-500 text-sm text-pretty">{milestone.description}</p>
                    </TimelineEntry>
                ))}
            </Timeline>

            <p className="mt-12 text-center">
                <Link
                    href={route("about")}
                    className="text-camel-200 decoration-camel-500 hover:text-camel-100 hover:decoration-camel-200 focus-visible:outline-camel-400 font-serif text-lg underline underline-offset-4 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    Read our full history
                </Link>
            </p>
        </Section>
    );
}
