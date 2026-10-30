import { useMemo } from "react";
import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import SectionNav from "@/Components/Navigation/SectionNav";
import PageContainer from "@/Components/PageContainer";
import Collapsible from "@/Components/Collapsible";
import ChapterTitle from "@/Components/Discord/ChapterTitle";
import DiscordMarkdown from "@/Components/Discord/DiscordMarkdown";
import { buildMentionLookup } from "@/Helpers/discordTags";
import formatDate from "@/Helpers/FormatDate";

/**
 * The reader URL for a chapter: the index for the introduction, otherwise its slug.
 */
function chapterHref(chapter) {
    return chapter.is_introduction
        ? route("raiding.information.index")
        : route("raiding.information.show", { chapter: chapter.slug });
}

/**
 * Links to every chapter in order, the current one highlighted.
 */
function ChapterLinks({ chapters, currentSlug, lookup }) {
    return (
        <ul className="border-amber-700/60 flex flex-col border-l">
            {chapters.map((chapter) => {
                const isCurrent = chapter.slug === currentSlug;

                return (
                    <li key={chapter.slug} className="-ml-px flex">
                        <Link
                            href={chapterHref(chapter)}
                            aria-current={isCurrent ? "page" : undefined}
                            className={`focus-visible:outline-amber-400 w-full border-l-2 py-1.5 pr-2 pl-4 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 ${
                                isCurrent
                                    ? "border-primary bg-primary/10 text-primary font-semibold"
                                    : "border-transparent text-gray-300 hover:border-amber-500 hover:text-white"
                            }`}
                        >
                            <ChapterTitle chapter={chapter} lookup={lookup} />
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * A link to a neighbouring chapter, labelled with its direction.
 */
function NeighbourLink({ chapter, direction, lookup }) {
    const isNext = direction === "next";

    return (
        <Link
            href={chapterHref(chapter)}
            rel={direction}
            className={`border-amber-700/60 hover:border-amber-500 hover:bg-brown-800 focus-visible:outline-amber-400 flex flex-col gap-1 rounded-md border px-4 py-3 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 ${
                isNext ? "sm:col-start-2 sm:items-end sm:text-right" : ""
            }`}
        >
            <span className="text-xs text-gray-400">{isNext ? "Next" : "Previous"}</span>
            <ChapterTitle chapter={chapter} lookup={lookup} className="text-amber-200" />
        </Link>
    );
}

export default function Show({ chapters, chapter, previous, next, mentions }) {
    const lookup = useMemo(() => buildMentionLookup(mentions), [mentions]);
    const introductionTitle = chapters.find((summary) => summary.is_introduction)?.title ?? chapter.title;

    return (
        <Master title={chapter.title}>
            <SharedHeader title={introductionTitle} backgroundClass="bg-illidan" />
            <SectionNav section="raiding" />
            <PageContainer>
                <div className="flex flex-col gap-8 md:grid md:grid-cols-[15rem_minmax(0,1fr)] md:gap-12">
                    <aside className="md:sticky md:top-28 md:self-start">
                        <div className="md:hidden">
                            <Collapsible key={chapter.slug} title="Chapters">
                                <nav aria-label="Chapters">
                                    <ChapterLinks chapters={chapters} currentSlug={chapter.slug} lookup={lookup} />
                                </nav>
                            </Collapsible>
                        </div>
                        <nav aria-label="Chapters" className="hidden md:block">
                            <ChapterLinks chapters={chapters} currentSlug={chapter.slug} lookup={lookup} />
                        </nav>
                    </aside>

                    <article className="max-w-3xl min-w-0">
                        <header className="border-amber-700/40 mb-4 border-b pb-4">
                            <ChapterTitle
                                chapter={chapter}
                                lookup={lookup}
                                as="h2"
                                className="font-serif text-3xl text-amber-200"
                            />
                            <p className="mt-2 text-sm text-gray-400">
                                Updated <time dateTime={chapter.updated_at}>{formatDate(chapter.updated_at).long}</time>
                            </p>
                        </header>

                        <DiscordMarkdown content={chapter.content} mentions={mentions} skipFirstHeading />

                        <nav aria-label="Previous and next chapters" className="mt-10 grid gap-4 sm:grid-cols-2">
                            {previous && <NeighbourLink chapter={previous} direction="prev" lookup={lookup} />}
                            {next && <NeighbourLink chapter={next} direction="next" lookup={lookup} />}
                        </nav>
                    </article>
                </div>
            </PageContainer>
        </Master>
    );
}
