import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import OnThisPage from "@/Components/OnThisPage";
import Band from "@/Components/Band/Band";
import BandHeading from "@/Components/Band/BandHeading";
import JoinDiscordButton from "@/Components/Discord/JoinDiscordButton";
import CountryFlag from "@/Components/CountryFlag";
import ItemLink from "@/Components/Items/ItemLink";
import Timeline, { TimelineEntry, TimelineHeading, TimelineLegendary } from "@/Components/Timeline/Timeline";

const SECTIONS = [
    { value: "classic", label: "World of Warcraft: Classic" },
    { value: "anniversary", label: "The Anniversary realms" },
    { value: "forever", label: "A new path on WoW Forever" },
    { value: "meet-ups", label: "Guild meet-ups" },
    { value: "mascot", label: "Our mascot" },
    { value: "join-us", label: "Join us" },
];

const COUNTRY_NAMES = {
    BH: "Bahrain",
    DK: "Denmark",
    FR: "France",
    GB: "United Kingdom",
    NL: "Netherlands",
    ZA: "South Africa",
};

const MEMBER_COUNTRIES = {
    Awak: "NL",
    Caldru: "GB",
    Deo: "GB",
    Dudum: "DK",
    Fizzywigs: "GB",
    Joe: "GB",
    Lok: "ZA",
    Madmoxx: "BH",
    Psykosis: "GB",
    Rendysavage: "GB",
    Schroddinger: "GB",
    Shaniko: "FR",
    Shiniko: "FR",
    Souljuice: "GB",
    Zar: "GB",
};

/**
 * A titled history section, labelled by its heading.
 */
function Section({ id, title, children }) {
    return (
        <section id={id} aria-labelledby={`${id}-heading`} className="flex flex-col gap-8">
            <BandHeading level={2} id={`${id}-heading`} headingClassName="text-2xl md:text-3xl">
                {title}
            </BandHeading>
            {children}
        </section>
    );
}

function Name({ children }) {
    return (
        <strong translate="no" className="font-semibold text-amber-100">
            {children}
        </strong>
    );
}

/**
 * A proper noun that browsers should leave untranslated.
 */
function ProperNoun({ children }) {
    return <span translate="no">{children}</span>;
}

/**
 * A highlighted member name followed by a small inline flag for their country.
 */
function Member({ children }) {
    const countryCode = MEMBER_COUNTRIES[children];

    return (
        <>
            <Name>{children}</Name>{" "}
            <CountryFlag
                countryCode={countryCode}
                title={COUNTRY_NAMES[countryCode]}
                className="inline-block align-[-0.125em]"
            />
        </>
    );
}

/**
 * A list of the members who attended a meet-up, optionally closed with "and more".
 */
function Attendees({ label, names, hasMore = false }) {
    return (
        <ul aria-label={label} className="grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3">
            {names.map((name) => (
                <li key={name}>
                    <Member>{name}</Member>
                </li>
            ))}
            {hasMore && <li className="text-gray-400 italic">and more</li>}
        </ul>
    );
}

export default function About() {
    return (
        <Master title="About Regrowth">
            <SharedHeader
                backgroundClass="bg-regrowth-gathering"
                title="About Regrowth"
                subtitle="Our history since 2021"
            />

            <Band tone="light">
                <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-16">
                    <OnThisPage sections={SECTIONS} className="hidden lg:flex lg:w-1/4 lg:shrink-0" />

                    <div className="flex max-w-prose min-w-0 flex-1 flex-col gap-14 leading-relaxed text-gray-300">
                        <p className="text-lg text-amber-100">
                            Regrowth is a World of Warcraft guild founded in April 2021, at home on Classic and now
                            WoW&nbsp;Forever. Since then we’ve raided through Burning Crusade Classic, Wrath of the Lich
                            King Classic and the Classic Anniversary realms, met up in pubs and back gardens across the
                            UK, and kept a social home together between expansions.
                        </p>

                        <Timeline>
                            <TimelineHeading id="classic" marker="classic">
                                World of Warcraft: Classic
                            </TimelineHeading>

                            <TimelineEntry
                                date="9 April 2021"
                                dateTime="2021-04-09"
                                title="A new beginning"
                                marker="classic"
                            >
                                <p>
                                    Regrowth was founded on <ProperNoun>Mirage Raceway</ProperNoun> after the fall of
                                    our previous guild, <ProperNoun>Low Level Fightclub</ProperNoun>, whose leadership
                                    had collapsed. The active members came together under the guild's three founders:{" "}
                                    <Name>Zar</Name>, <Name>Pjolk</Name> and <Name>Obscured</Name>.
                                </p>
                            </TimelineEntry>

                            <TimelineLegendary
                                date="2021"
                                dateTime="2021"
                                title="The guild's first legendary"
                                marker="classic"
                                item={{
                                    id: 19019,
                                    name: "Thunderfury, Blessed Blade of the Windseeker",
                                    icon: "inv_sword_39",
                                    domain: "classic",
                                }}
                                sticker={{
                                    src: "/images/sticker_zarlei_with_thunderfury.webp",
                                    alt: "A cartoon sticker of Zar holding Thunderfury",
                                    width: 2224,
                                    height: 2065,
                                }}
                            >
                                <p>
                                    <Name>Zar</Name> completed Thunderfury, Blessed Blade of the Windseeker, the guild's
                                    first legendary.
                                </p>
                            </TimelineLegendary>

                            <TimelineEntry
                                date="June 2021"
                                dateTime="2021-06"
                                title="The Burning Crusade"
                                marker="classic"
                            >
                                <p>
                                    We stepped into TBC, where <Name>Pjolk</Name> stepped back from playing and{" "}
                                    <Name>Lok</Name> took up his leadership role. We raided throughout the entire
                                    expansion and made excellent progress on every raid and boss.
                                </p>
                            </TimelineEntry>

                            <TimelineEntry
                                date="November 2021"
                                dateTime="2021-11"
                                title="Season of Mastery"
                                marker="classic"
                            >
                                <p>
                                    Regrowth made a brief appearance on the Alliance side in Season of Mastery. A
                                    handful of us levelled to around 40–50, but our hearts remained in TBC.
                                </p>
                            </TimelineEntry>

                            <TimelineEntry
                                date="September 2022"
                                dateTime="2022-09"
                                title="Wrath of the Lich King"
                                marker="classic"
                            >
                                <p>
                                    We carried on as a raiding guild into Wrath on{" "}
                                    <ProperNoun>Mirage Raceway</ProperNoun> as Horde. As raid numbers dwindled towards
                                    the end of the expansion, a number of our members joined forces with the raid team
                                    of <ProperNoun>The Muffin Vendor</ProperNoun>. The friendships we’d built remained
                                    strong, and Regrowth stayed active as a social home through the final phase of
                                    Wrath.
                                </p>
                            </TimelineEntry>

                            <TimelineEntry
                                date="November 2023"
                                dateTime="2023-11"
                                title="Season of Discovery"
                                marker="classic"
                            >
                                <p>
                                    Regrowth reunited for the launch of Season of Discovery, heading back to the
                                    Alliance. We completed the first phase of raiding and cleared Blackfathom Deeps, but
                                    the guild’s heart wasn’t in it and we didn’t push far into the next phase. Our focus
                                    remained on raiding in Wrath.
                                </p>
                            </TimelineEntry>

                            <TimelineEntry date="May 2024" dateTime="2024-05" title="Cataclysm" marker="classic">
                                <p>
                                    Regrowth continued on <ProperNoun>Mirage Raceway</ProperNoun> as a social guild
                                    through Cataclysm, while a number of us kept raiding with{" "}
                                    <ProperNoun>The Muffin Vendor</ProperNoun> and other guilds.
                                </p>
                            </TimelineEntry>

                            <TimelineHeading id="anniversary" marker="anniversary">
                                The Anniversary realms
                            </TimelineHeading>

                            <TimelineEntry
                                date="November 2024"
                                dateTime="2024-11"
                                title="WoW Anniversary servers"
                                marker="anniversary"
                            >
                                <p>
                                    Only a week after WoW Anniversary servers were announced, Regrowth was reborn on{" "}
                                    <ProperNoun>Thunderstrike</ProperNoun> as Alliance. Former members returned, and we
                                    recruited plenty of new faces along the journey to level 60, building the raid and
                                    social roster needed to take on 40-man content once again.
                                </p>
                            </TimelineEntry>

                            <TimelineLegendary
                                date="2025"
                                dateTime="2025"
                                title="Sulfuras"
                                marker="anniversary"
                                item={{
                                    id: 17182,
                                    name: "Sulfuras, Hand of Ragnaros",
                                    icon: "inv_hammer_unique_sulfuras",
                                    domain: "classic",
                                }}
                            >
                                <p>
                                    <Name>Izepo</Name> claimed the{" "}
                                    <ItemLink
                                        id="17204"
                                        name="Eye of Sulfuras"
                                        icon="inv_misc_gem_pearl_05"
                                        quality="legendary"
                                        domain="classic"
                                    />{" "}
                                    and used it to forge the iconic hammer.
                                </p>
                            </TimelineLegendary>

                            <TimelineLegendary
                                date="18 July 2025"
                                dateTime="2025-07-18"
                                title="Scarab Lord"
                                marker="anniversary"
                                item={{
                                    id: 21176,
                                    name: "Black Qiraji Resonating Crystal",
                                    icon: "inv_misc_qirajicrystal_05",
                                    domain: "classic",
                                }}
                                sticker={{
                                    src: "/images/sticker_loktorious_scarab_lord.webp",
                                    alt: "A cartoon sticker of Loktorious as Scarab Lord",
                                    width: 1971,
                                    height: 1822,
                                }}
                            >
                                <p>
                                    <Name>Loktorious</Name> completed the Scepter of the Shifting Sands quest chain to
                                    obtain the Black Qiraji Resonating Crystal and claim the title of Scarab Lord.
                                </p>
                            </TimelineLegendary>

                            <TimelineEntry
                                date="July 2025"
                                dateTime="2025-07"
                                title="Mists of Pandaria Classic"
                                marker="classic"
                            >
                                <p>
                                    When MoP Classic launched, Regrowth was spread across several flavours of Warcraft,
                                    with the majority holding strong alongside our newer members on the Anniversary
                                    servers.
                                </p>
                            </TimelineEntry>

                            <TimelineLegendary
                                date="July 2025"
                                dateTime="2025-07"
                                title="Thunderfury, again"
                                marker="anniversary"
                                item={{
                                    id: 19019,
                                    name: "Thunderfury, Blessed Blade of the Windseeker",
                                    icon: "inv_sword_39",
                                    domain: "classic",
                                }}
                                sticker={{
                                    src: "/images/sticker_shiniko_thunderfury.webp",
                                    alt: "A cartoon sticker of Shiniko holding Thunderfury",
                                    width: 1872,
                                    height: 2364,
                                }}
                            >
                                <p>
                                    On a crazy night in Molten Core, <Name>Shiniko</Name> made a bet with several
                                    raiders that we wouldn't get both bindings in a single night. He lost the bet, but
                                    completed the guild's second Thunderfury.
                                </p>
                                <p>
                                    Rumour has it he is still accepting donations of{" "}
                                    <ItemLink
                                        id={4784}
                                        name="Lifeless Stone"
                                        icon="inv_stone_10"
                                        quality="poor"
                                        domain="classic"
                                    />{" "}
                                    to pay off his debt.
                                </p>
                            </TimelineLegendary>

                            <TimelineLegendary
                                date="16 December 2025"
                                dateTime="2025-12-16"
                                title="Atiesh"
                                marker="anniversary"
                                item={{
                                    id: 22589,
                                    name: "Atiesh, Greatstaff of the Guardian",
                                    icon: "inv_staff_medivh",
                                    domain: "classic",
                                }}
                                sticker={{
                                    src: "/images/sticker_fizzywigs_with_atiesh.webp",
                                    alt: "A cartoon sticker of Fizzywigs holding Atiesh",
                                    width: 1664,
                                    height: 2042,
                                    isSmall: true,
                                }}
                            >
                                <p>
                                    <Name>Fizzywigs</Name> pulled off an incredible feat and collected all fourty{" "}
                                    <ItemLink
                                        id="22726"
                                        name="Splinters of Atiesh"
                                        icon="inv_qiraj_jewelblessed"
                                        quality="legendary"
                                        domain="classic"
                                    />
                                    {", "}
                                    as well as the head and base, and was able to reform Medivh's legendary staff.
                                </p>
                            </TimelineLegendary>

                            <TimelineEntry
                                date="February 2026"
                                dateTime="2026-02"
                                title="Anniversary TBC"
                                marker="anniversary"
                            >
                                <p>
                                    We moved on to WoW Anniversary TBC and raided through all of its content until
                                    October 2026.
                                </p>
                            </TimelineEntry>

                            <TimelineHeading id="forever" marker="forever">
                                A new path on WoW Forever
                            </TimelineHeading>

                            <TimelineEntry
                                date="4 November 2026"
                                dateTime="2026-11-04"
                                title="Launch day"
                                marker="forever"
                                isLatest
                            >
                                <p>
                                    As the WoW&nbsp;Forever servers open, Regrowth shifts its focus to a fresh start on
                                    the Normal ruleset as Horde, returning to the faction where the guild was founded.
                                    We remain on the Anniversary realms as a social home for anyone who wants to stay.
                                </p>
                            </TimelineEntry>
                        </Timeline>

                        <Section id="meet-ups" title="Guild meet-ups">
                            <Timeline>
                                <TimelineEntry date="March 2022" dateTime="2022-03" title="Sheffield">
                                    <p>
                                        Our first in-person meet-up took place in Sheffield, with a night out and plenty
                                        of drinks.
                                    </p>
                                    <Attendees
                                        label="Sheffield attendees"
                                        names={["Caldru", "Deo", "Dudum", "Lok", "Zar"]}
                                    />
                                </TimelineEntry>

                                <TimelineEntry date="July 2022" dateTime="2022-07" title="The Lincolnshire BBQ">
                                    <p>
                                        Our second, bigger meet-up took place in the exotic setting of Lincolnshire,
                                        hosted by <Name>Zar</Name> and <Name>Caldru</Name>, with a BBQ, plenty of
                                        drinks, a palm tree and a sweet potato. Guests travelled from Bahrain, South
                                        Africa, Denmark and the Netherlands to join many more from across the UK. Those
                                        who couldn’t make the journey tuned in via a Discord livestream. It was
                                        certainly memorable in a number of ways!
                                    </p>
                                    <Attendees
                                        label="Lincolnshire BBQ attendees"
                                        names={[
                                            "Awak",
                                            "Caldru",
                                            "Deo",
                                            "Dudum",
                                            "Joe",
                                            "Lok",
                                            "Madmoxx",
                                            "Psykosis",
                                            "Rendysavage",
                                            "Schroddinger",
                                            "Zar",
                                        ]}
                                        hasMore
                                    />
                                </TimelineEntry>

                                <TimelineEntry date="January 2026" dateTime="2026-01" title="London">
                                    <p>
                                        <Name>Lok</Name> and <Name>Fizzywigs</Name> were joined by friends as they
                                        celebrated their birthdays in central London.
                                    </p>
                                    <Attendees
                                        label="London attendees"
                                        names={["Dudum", "Fizzywigs", "Lok", "Shaniko", "Shiniko", "Souljuice"]}
                                    />
                                </TimelineEntry>

                                <TimelineEntry date="October 2026" dateTime="2026-10" title="Hertfordshire">
                                    <p>We met up for a pub lunch just outside the M25 in Hertfordshire.</p>
                                    <Attendees
                                        label="Hertfordshire attendees"
                                        names={["Caldru", "Fizzywigs", "Souljuice", "Zar"]}
                                    />
                                </TimelineEntry>
                            </Timeline>
                        </Section>
                    </div>
                </div>
            </Band>

            <Band tone="light" id="mascot" aria-labelledby="mascot-heading" maxWidth="max-w-5xl">
                <div className="flex flex-col items-center gap-10 md:flex-row md:gap-14">
                    <div className="flex flex-1 flex-col gap-4 leading-relaxed text-gray-300">
                        <BandHeading level={2} id="mascot-heading">
                            Meet <ProperNoun>Jaina</ProperNoun>, our mascot
                        </BandHeading>
                        <p>
                            <Name>Jaina</Name> is a fluffy white Samoyed and the guild’s much-loved mascot. She’s also
                            one of the hardest-working members of the guild: as our Discord bot, she posts our raid
                            compositions and important announcements to the Regrowth Discord server.
                        </p>
                        <p>
                            When she isn’t keeping the guild organised, she can often be heard in the background during
                            raids, adding her own commentary to the boss fights.
                        </p>
                    </div>
                    <img
                        src="/images/sticker_jaina_on_her_back.webp"
                        alt="A cartoon sticker of Jaina the Samoyed rolling on her back with her paws in the air"
                        width="1662"
                        height="943"
                        loading="lazy"
                        className="h-auto w-full max-w-sm shrink-0 md:w-2/5"
                    />
                </div>
            </Band>

            <Band
                tone="shaded"
                edge="both"
                id="join-us"
                aria-labelledby="join-us-heading"
                className="text-center"
                maxWidth="max-w-2xl"
            >
                <BandHeading level={2} id="join-us-heading" className="mb-4">
                    Join us on WoW&nbsp;Forever
                </BandHeading>
                <p className="mx-auto mb-4 max-w-xl leading-relaxed text-gray-300">
                    The next chapter of Regrowth is being written right now, as Horde on the Normal ruleset. Whether
                    you’re a returning friend or a new face, there’s a place for you in the raids, the dungeons and the
                    long evenings in between.
                </p>
                <p className="mx-auto mb-8 max-w-xl font-serif text-xl text-amber-200">
                    Join our Discord and become part of Regrowth.
                </p>
                <JoinDiscordButton />
            </Band>
        </Master>
    );
}
