import Section from "./Section";
import DisplayHeading from "./DisplayHeading";
import OfficerProfile from "./OfficerProfile";

/**
 * Staggered *visible character* heights in pixels, cycled by index so the
 * row has the uneven silhouette of the original design while staying stable
 * between renders. These describe how tall each officer should look, not
 * the render image's own height — see OfficerProfile for why that
 * distinction matters.
 */
const VISIBLE_HEIGHTS = [172, 236, 204, 258, 194, 246, 214, 204];

/**
 * Horizontal gap between columns, in pixels. Columns (not images) carry the
 * spacing, because column width is fixed while each render's own rendered
 * width varies with its visible fraction (see OfficerProfile) — offsetting
 * by a percentage of the image itself, as a previous version of this
 * component did, produced wildly inconsistent gaps once renders were scaled
 * by *character* height rather than canvas height. Every render stays
 * centred in its column (OfficerProfile always centres via a constant -50%
 * translate); this is what gives every podium the same breathing room.
 */
const COLUMN_GAP_PX = 24;

/** Layering per position so nearer (taller-index) officers read on top. */
const Z_INDEXES = ["z-0", "z-10", "z-20", "z-30"];

/**
 * The officer team, laid out as character renders standing on a shared
 * baseline at staggered heights — the "Inner Circle" treatment from the
 * earlier theorder-website project.
 *
 * Desktop gets the single staggered row. Mobile gets a wrapped grid rather
 * than a carousel, so every officer is reachable without interaction.
 *
 * `renders` is the deferred officerRenders map — `{ url, visibleTop,
 * visibleBottom }` per officer, or null — and is undefined until it
 * resolves, so every lookup must tolerate that.
 */
export default function OfficerTeam({ officers = [], renders }) {
    if (officers.length === 0) {
        return null;
    }

    const isLoading = renders === undefined;
    const renderFor = (name) => renders?.[name] ?? null;

    // Some races (Tauren) read visibly oversized next to the rest of the
    // team at the same visible-character height, so their render is scaled
    // down independently of the row's per-position stagger.
    const visibleHeightFor = (name, index) => {
        const baseHeight = VISIBLE_HEIGHTS[index % VISIBLE_HEIGHTS.length];
        return renderFor(name)?.isLargeRace ? baseHeight / 2 : baseHeight;
    };

    return (
        <Section tone="parchment" edge="bottom" edgeTone="mid" className="py-16 md:py-24">
            <div className="container mx-auto px-4">
                <DisplayHeading level={2} eyebrow="Who leads us" className="mb-4 text-center">
                    Meet the Officers
                </DisplayHeading>

                <p className="text-camel-400 mx-auto mb-12 max-w-2xl text-center md:mb-16">
                    Regrowth is steered by a team of officers who organise the raids, settle the loot and keep the
                    guild running. Here they all are.
                </p>

                {/* Desktop: one staggered row on a shared baseline, each render
                    centred in its own column with a fixed gap between
                    podiums. */}
                <div className="hidden items-end lg:flex">
                    {officers.map((officer, index) => (
                        <OfficerProfile
                            key={officer.name}
                            officer={officer}
                            render={renderFor(officer.name)}
                            isLoading={isLoading}
                            visibleHeight={visibleHeightFor(officer.name, index)}
                            columnStyle={index > 0 ? { marginLeft: `${COLUMN_GAP_PX}px` } : undefined}
                            zIndexClass={Z_INDEXES[index % Z_INDEXES.length]}
                        />
                    ))}
                </div>

                {/* Mobile and tablet: a wrapped grid, no carousel. */}
                <div className="grid grid-cols-2 gap-x-4 gap-y-10 sm:grid-cols-3 lg:hidden">
                    {officers.map((officer) => (
                        <OfficerProfile
                            key={officer.name}
                            officer={officer}
                            render={renderFor(officer.name)}
                            isLoading={isLoading}
                            visibleHeight={renderFor(officer.name)?.isLargeRace ? 80 : 160}
                        />
                    ))}
                </div>
            </div>
        </Section>
    );
}
