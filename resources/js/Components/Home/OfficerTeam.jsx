import { useCallback, useEffect, useState } from "react";
import useEmblaCarousel from "embla-carousel-react";
import Section from "@/Themes/Forever/Section";
import DisplayHeading from "@/Themes/Forever/DisplayHeading";
import CountryFlag from "@/Components/CountryFlag";

/** Layering per position so nearer (taller-index) officers read on top. */
const Z_INDEXES = ["z-0", "z-10", "z-20", "z-30"];

/**
 * Staggered visible-character height (px) for a desktop row position, for an
 * uneven silhouette. A sine wave rather than a fixed lookup table so it
 * scales to any officer count without needing to be kept in sync with the
 * roster.
 */
const STAGGER_BASE_HEIGHT = 215;
const STAGGER_AMPLITUDE = 43;
const STAGGER_PERIOD = 5;

function staggerHeight(index) {
    return Math.round(STAGGER_BASE_HEIGHT + STAGGER_AMPLITUDE * Math.sin((index / STAGGER_PERIOD) * Math.PI * 2));
}

/**
 * A single officer's character render on the shared baseline, with a
 * silhouette fallback until the deferred render arrives (or permanently, if
 * it 404s or the officer has none).
 *
 * Blizzard's renders share one canvas size, but the character fills a
 * different fraction of it per race/pose, so the image is scaled by its
 * visible band (`visibleTop`/`visibleBottom`, see AttachRenderToCharacter)
 * rather than by canvas height, and shifted so only the excess canvas above
 * the character overflows.
 */
function OfficerProfile({
    officer,
    render = null,
    isLoading = false,
    visibleHeight = 224,
    zIndexClass = "z-0",
    basisClassName = "lg:basis-[calc(20%-1.2rem)]",
}) {
    const [failed, setFailed] = useState(false);

    // The deferred prop can deliver a render after first paint, and a later
    // visit can replace one that previously 404'd.
    useEffect(() => setFailed(false), [render]);

    const showRender = render !== null && !failed;

    const visibleFraction = showRender ? Math.max(render.visibleBottom - render.visibleTop, 0.01) : 1;
    const imageHeight = visibleHeight / visibleFraction;
    const bottomOffset = showRender ? (1 - render.visibleBottom) * imageHeight : 0;

    return (
        <div className={`group flex flex-1 flex-col items-center text-center lg:shrink-0 lg:grow-0 ${basisClassName}`}>
            <div className={`relative w-full ${zIndexClass} hover:z-20`} style={{ height: `${visibleHeight}px` }}>
                {showRender ? (
                    <img
                        src={render.url}
                        alt={`${officer.name}, guild officer`}
                        loading="lazy"
                        onError={() => setFailed(true)}
                        className="absolute left-1/2 w-auto max-w-none -translate-x-1/2 object-contain drop-shadow-[0_8px_20px_rgba(0,0,0,0.55)] transition-transform duration-300 group-hover:-translate-y-1"
                        style={{ height: `${imageHeight}px`, bottom: `${-bottomOffset}px` }}
                    />
                ) : (
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 100 140"
                        preserveAspectRatio="xMidYMax meet"
                        fill="currentColor"
                        className={`text-ground-600 absolute bottom-0 left-1/2 h-full w-auto max-w-none -translate-x-1/2 drop-shadow-[0_8px_20px_rgba(0,0,0,0.55)] transition-transform duration-300 group-hover:-translate-y-1 ${isLoading ? "animate-pulse" : ""}`}
                    >
                        <circle cx="50" cy="42" r="24" />
                        <path d="M50 74c-22 0-38 15-42 38-1 6 3 10 9 10h66c6 0 10-4 9-10-4-23-20-38-42-38z" />
                    </svg>
                )}
            </div>

            {/* Baseline every officer stands on. */}
            <div className="via-primary/50 relative z-10 h-px w-full bg-linear-to-r from-transparent to-transparent" />

            <h3 className="text-ink-200 mt-4 font-serif text-xl font-normal">{officer.name}</h3>

            <p className="text-ink-400 mt-1 flex items-center justify-center gap-1.5 text-sm">
                <CountryFlag countryCode={officer.country_code} />
                <span className="sr-only">{officer.demonym}</span>
            </p>
        </div>
    );
}

/**
 * The officer team: a staggered baseline row on desktop, a swipeable
 * one-officer-per-slide carousel on mobile/tablet.
 *
 * `renders` is the deferred officerRenders map (`{ url, visibleTop,
 * visibleBottom }` per officer, or null) and is undefined until it
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

    // Must come after all hooks above (Rules of Hooks).
    if (officers.length === 0) {
        return null;
    }

    const isLoading = renders === undefined;
    const renderFor = (name) => renders?.[name] ?? null;

    // Small races (dwarves/gnomes) read oversized at the same visible height, so scale them down.
    const visibleHeightFor = (name, index) => {
        const baseHeight = staggerHeight(index);
        return renderFor(name)?.isSmallRace ? baseHeight / 2 : baseHeight;
    };

    const carouselVisibleHeightFor = (name) => (renderFor(name)?.isSmallRace ? 100 : 200);

    return (
        <Section tone="parchment" edge="bottom" edgeTone="mid">
            <DisplayHeading level={2} eyebrow="Who leads us" className="mb-4 text-center">
                Meet the Officers
            </DisplayHeading>

            <p className="text-ink-200 mx-auto mb-12 max-w-2xl text-center md:mb-16">
                Regrowth is steered by a team of officers who organise the raids, settle the loot and keep the guild
                running. Here they all are.
            </p>

            <div className="hidden w-full flex-wrap items-end justify-center gap-x-6 gap-y-10 lg:flex">
                {officers.map((officer, index) => (
                    <OfficerProfile
                        key={officer.name}
                        officer={officer}
                        render={renderFor(officer.name)}
                        isLoading={isLoading}
                        visibleHeight={visibleHeightFor(officer.name, index)}
                        zIndexClass={Z_INDEXES[index % Z_INDEXES.length]}
                        basisClassName="lg:basis-[calc(20%-1.2rem)] xl:basis-[calc(12.5%-1.3125rem)]"
                    />
                ))}
            </div>

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
                        <CarouselArrowButton direction="prev" onClick={scrollPrev} label="Previous officer" />

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
                                        index === selectedIndex ? "bg-ink-300" : "bg-ink-600/50 hover:bg-ink-500"
                                    }`}
                                />
                            ))}
                        </div>

                        <CarouselArrowButton direction="next" onClick={scrollNext} label="Next officer" />
                    </div>
                )}
            </div>
        </Section>
    );
}

const ARROW_PATHS = {
    prev: "M15 5l-7 7 7 7",
    next: "M9 5l7 7-7 7",
};

function CarouselArrowButton({ direction, onClick, label }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="text-ink-400 hover:text-ink-200 focus-visible:outline-ink-500 rounded-full p-1 transition-colors focus-visible:outline focus-visible:outline-2"
        >
            <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                className="h-5 w-5"
            >
                <path d={ARROW_PATHS[direction]} strokeLinecap="round" strokeLinejoin="round" />
            </svg>
        </button>
    );
}
