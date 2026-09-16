import { useCallback, useEffect, useState } from "react";
import useEmblaCarousel from "embla-carousel-react";
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
 * Desktop gets the single staggered row. Mobile and tablet get a swipeable
 * carousel, one officer centred per slide.
 *
 * `renders` is the deferred officerRenders map — `{ url, visibleTop,
 * visibleBottom }` per officer, or null — and is undefined until it
 * resolves, so every lookup must tolerate that.
 */
export default function OfficerTeam({ officers = [], renders }) {
    const [emblaRef, emblaApi] = useEmblaCarousel({
        loop: false,
        align: "center",
        containScroll: "trimSnaps",
        dragFree: false,
    });

    const [selectedIndex, setSelectedIndex] = useState(0);
    const onSelect = useCallback((api) => setSelectedIndex(api.selectedScrollSnap()), []);

    useEffect(() => {
        if (!emblaApi) {
            return;
        }

        onSelect(emblaApi);
        emblaApi.on("select", onSelect);
        emblaApi.on("reInit", onSelect);

        return () => {
            emblaApi.off("select", onSelect);
            emblaApi.off("reInit", onSelect);
        };
    }, [emblaApi, onSelect]);

    const scrollTo = useCallback((index) => emblaApi?.scrollTo(index), [emblaApi]);
    const scrollPrev = useCallback(() => emblaApi?.scrollPrev(), [emblaApi]);
    const scrollNext = useCallback(() => emblaApi?.scrollNext(), [emblaApi]);

    // The desktop row and the mobile carousel both need every hook above
    // called unconditionally (Rules of Hooks), so this guard has to sit
    // after them rather than before, even though it used to be the first
    // line in the component.
    if (officers.length === 0) {
        return null;
    }

    const isLoading = renders === undefined;
    const renderFor = (name) => renders?.[name] ?? null;

    // Some races (dwarves/gnomes) read visibly oversized next to the rest of the
    // team at the same visible-character height, so their render is scaled
    // down independently of the row's per-position stagger.
    const visibleHeightFor = (name, index) => {
        const baseHeight = VISIBLE_HEIGHTS[index % VISIBLE_HEIGHTS.length];
        return renderFor(name)?.isLargeRace ? baseHeight / 2 : baseHeight;
    };

    // Carousel slides are full-width (or near it), with far more headroom
    // relative to width than a grid cell had, so they use taller base
    // heights than the desktop row's VISIBLE_HEIGHTS stagger.
    const carouselVisibleHeightFor = (name) => (renderFor(name)?.isLargeRace ? 100 : 200);

    return (
        <Section tone="parchment" edge="bottom" edgeTone="mid" className="py-16 md:py-24">
            <div className="container mx-auto px-4">
                <DisplayHeading level={2} eyebrow="Who leads us" className="mb-4 text-center">
                    Meet the Officers
                </DisplayHeading>

                <p className="text-camel-200 mx-auto mb-12 max-w-2xl text-center md:mb-16">
                    Regrowth is steered by a team of officers who organise the raids, settle the loot and keep the guild
                    running. Here they all are.
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

                {/* Mobile and tablet: a swipeable single-officer carousel (peeking
                    neighbours from `sm` up), replacing the old wrapped grid so the
                    section reads the same "step through officers" way the original
                    theorder-website Bootstrap carousel did. */}
                <div className="lg:hidden">
                    <div className="overflow-hidden" ref={emblaRef}>
                        <div className="flex">
                            {officers.map((officer) => (
                                <div
                                    key={officer.name}
                                    className="flex min-w-0 flex-[0_0_100%] items-end justify-center px-4 sm:flex-[0_0_85%]"
                                >
                                    <OfficerProfile
                                        officer={officer}
                                        render={renderFor(officer.name)}
                                        isLoading={isLoading}
                                        visibleHeight={carouselVisibleHeightFor(officer.name)}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {officers.length > 1 && (
                        <div className="mt-8 flex items-center justify-center gap-6">
                            <button
                                type="button"
                                onClick={scrollPrev}
                                aria-label="Previous officer"
                                className="text-camel-400 hover:text-camel-200 focus-visible:outline-camel-500 rounded-full p-1 transition-colors focus-visible:outline focus-visible:outline-2"
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    className="h-5 w-5"
                                >
                                    <path d="M15 5l-7 7 7 7" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </button>

                            <div className="flex items-center gap-2" role="tablist" aria-label="Officers">
                                {officers.map((officer, index) => (
                                    <button
                                        key={officer.name}
                                        type="button"
                                        role="tab"
                                        aria-selected={index === selectedIndex}
                                        aria-label={`Show ${officer.name}`}
                                        onClick={() => scrollTo(index)}
                                        className={`h-2.5 w-2.5 rounded-full transition-colors ${
                                            index === selectedIndex
                                                ? "bg-camel-300"
                                                : "bg-camel-600/50 hover:bg-camel-500"
                                        }`}
                                    />
                                ))}
                            </div>

                            <button
                                type="button"
                                onClick={scrollNext}
                                aria-label="Next officer"
                                className="text-camel-400 hover:text-camel-200 focus-visible:outline-camel-500 rounded-full p-1 transition-colors focus-visible:outline focus-visible:outline-2"
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    className="h-5 w-5"
                                >
                                    <path d="M9 5l7 7-7 7" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </Section>
    );
}
