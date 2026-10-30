import { useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import AutosavedSortableList from "@/Components/Datasets/AutosavedSortableList";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import EmptyState from "@/Components/EmptyState";
import { SecondaryButton } from "@/Components/FormControls";
import ChapterTitle from "@/Components/Discord/ChapterTitle";
import ChapterRow from "@/Components/RaidInformation/ChapterRow";
import SharedHeader from "@/Components/SharedHeader";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import { buildMentionLookup } from "@/Helpers/discordTags";
import formatDate from "@/Helpers/FormatDate";
import { AutosaveProvider } from "@/Hooks/useAutosave";
import Master from "@/Layouts/Master";

/**
 * "{n} days left" for a trashed chapter.
 *
 * @param {number} days
 * @returns {string}
 */
function daysLeft(days) {
    return `${days} ${days === 1 ? "day" : "days"} left`;
}

export default function Index({ chapters, trashed, mentions }) {
    const lookup = useMemo(() => buildMentionLookup(mentions), [mentions]);
    const introduction = chapters.find((chapter) => chapter.is_introduction) ?? null;
    const orderedChapters = useMemo(() => chapters.filter((chapter) => !chapter.is_introduction), [chapters]);
    const [restoringSlug, setRestoringSlug] = useState(null);

    function restore(chapter) {
        setRestoringSlug(chapter.slug);
        router.post(
            route("management.raid-information.restore", chapter.slug),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRestoringSlug(null),
            },
        );
    }

    return (
        <Master title="Raid information">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Raid information" />

            <AutosaveProvider>
                <IndexLayout
                    intro="The raid handbook members read on the site. The introduction always comes first; drag the other chapters into the order members should read them."
                    addHref={route("management.raid-information.create")}
                    addLabel="New chapter"
                    layout="stack"
                    isEmpty={chapters.length === 0}
                    emptyIcon="book"
                    emptyMessage="No chapters yet. Add the first one."
                >
                    <section aria-labelledby="chapters-heading" className="flex flex-col gap-4">
                        <h2 id="chapters-heading" className="font-serif text-2xl text-white">
                            Chapters
                        </h2>
                        {introduction && (
                            <div>
                                <ChapterRow chapter={introduction} lookup={lookup} />
                            </div>
                        )}
                        {orderedChapters.length > 0 && (
                            <AutosavedSortableList
                                items={orderedChapters}
                                getKey={(chapter) => chapter.slug}
                                getLabel={(chapter) => chapter.title}
                                url={route("management.raid-information.reorder")}
                                method="put"
                                field="slugs"
                                saveKey="raid-information-order"
                                label="Chapters after the introduction"
                                className="flex flex-col gap-4"
                                renderItem={(chapter, { dragHandle }) => (
                                    <ChapterRow chapter={chapter} lookup={lookup} dragHandle={dragHandle} />
                                )}
                            />
                        )}
                    </section>

                    <section aria-labelledby="trashed-heading" className="flex flex-col gap-4">
                        <div className="flex flex-col gap-1">
                            <h2 id="trashed-heading" className="font-serif text-2xl text-white">
                                Recently deleted
                            </h2>
                            <p className="text-sm text-gray-300">
                                Deleted chapters stay here for 30 days. Restoring one puts it at the end of the list.
                            </p>
                        </div>
                        {trashed.length === 0 ? (
                            <EmptyState
                                icon="trash-restore"
                                iconStyle="light"
                                message="No recently deleted chapters."
                            />
                        ) : (
                            <Table>
                                <TableHead>
                                    <tr>
                                        <TableHeader className="text-left">Chapter</TableHeader>
                                        <TableHeader className="text-left">Deleted</TableHeader>
                                        <TableHeader className="text-left">Time left to restore</TableHeader>
                                        <TableHeader className="text-right">
                                            <span className="sr-only">Actions</span>
                                        </TableHeader>
                                    </tr>
                                </TableHead>
                                <TableBody>
                                    {trashed.map((chapter) => (
                                        <TableRow key={chapter.slug}>
                                            <TableCell>
                                                <ChapterTitle
                                                    chapter={chapter}
                                                    lookup={lookup}
                                                    className="text-white"
                                                />
                                            </TableCell>
                                            <TableCell className="text-gray-300">
                                                <time dateTime={chapter.trashed_at}>
                                                    {formatDate(chapter.trashed_at).medium}
                                                </time>
                                            </TableCell>
                                            <TableCell className="text-gray-300">
                                                {daysLeft(chapter.days_remaining)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <SecondaryButton
                                                    onClick={() => restore(chapter)}
                                                    processing={restoringSlug === chapter.slug}
                                                    disabled={restoringSlug !== null}
                                                >
                                                    {restoringSlug === chapter.slug ? "Restoring…" : "Restore"}
                                                    <span className="sr-only"> {chapter.title}</span>
                                                </SecondaryButton>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </section>
                </IndexLayout>
            </AutosaveProvider>
        </Master>
    );
}
