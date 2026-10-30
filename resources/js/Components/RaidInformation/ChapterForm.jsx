import { useRef, useState } from "react";
import { Link, useForm } from "@inertiajs/react";
import DiscordMarkdown from "@/Components/Discord/DiscordMarkdown";
import DiscordMarkdownEditor from "@/Components/Discord/DiscordMarkdownEditor";
import Icon from "@/Components/FontAwesome/Icon";
import { FieldError, PrimaryButton, SaveButton, SecondaryButton, linkClassName } from "@/Components/FormControls";
import cn from "@/Helpers/cn";

const NEW_CHAPTER_CONTENT = "## ";

const NO_ROOM_MESSAGE = "No room for that — the chapter is at 2,000 characters.";

const DELETED_MESSAGE = "This chapter was deleted while you were editing. Copy your text before leaving.";

/**
 * The trim notice for `cutCount` characters cut from a paste, or the no-room
 * message when a change was rejected outright.
 *
 * @param {number} cutCount
 * @returns {string}
 */
function trimMessage(cutCount) {
    if (cutCount === 0) {
        return NO_ROOM_MESSAGE;
    }

    return `Pasted text was ${cutCount.toLocaleString("en-GB")} characters too long, so the end was cut.`;
}

/**
 * A raid information chapter's editor beside a live preview (stacked below
 * `lg`). With a `chapter` it saves the chapter with its `base_hash`, takes the
 * saved hash after each save, offers the newer version when someone else saved
 * first, and keeps the text when the chapter was deleted meanwhile. Without a
 * `chapter` it adds a new chapter, starting from "## ".
 *
 * @param {Object} props
 * @param {Object|null} [props.chapter] - The chapter being edited, or null to add one
 * @param {{ channels: Array, roles: Array, emoji: Array }} props.mentions - The mention directory
 * @param {string} props.submitLabel - The save button's label
 * @param {string} props.processingLabel - The save button's label while saving
 */
export default function ChapterForm({ chapter = null, mentions, submitLabel, processingLabel }) {
    const isEditing = chapter !== null;
    const form = useForm(
        isEditing ? { content: chapter.content, base_hash: chapter.hash } : { content: NEW_CHAPTER_CONTENT },
    );
    const { data, setData, errors, clearErrors, processing } = form;
    const [trimNotice, setTrimNotice] = useState("");
    const [isDeleted, setIsDeleted] = useState(false);
    const isTrimmingEdit = useRef(false);
    const hasConflict = isEditing && Boolean(errors.base_hash);

    function handleTrim(cutCount) {
        isTrimmingEdit.current = true;
        queueMicrotask(() => {
            isTrimmingEdit.current = false;
        });
        setTrimNotice(trimMessage(cutCount));
    }

    function handleChange(content) {
        if (!isTrimmingEdit.current) {
            setTrimNotice("");
        }

        setData("content", content);
    }

    function takeTheirVersion() {
        setData({ content: chapter.content, base_hash: chapter.hash });
        clearErrors("base_hash");
    }

    function keepMine() {
        setData("base_hash", chapter.hash);
        clearErrors("base_hash");
    }

    function submit(event) {
        event.preventDefault();

        if (!isEditing) {
            form.post(route("management.raid-information.store"), { preserveScroll: true });

            return;
        }

        form.patch(route("management.raid-information.update", chapter.slug), {
            preserveScroll: true,
            onSuccess: (page) => setData("base_hash", page.props.chapter.hash),
            onHttpException: (response) => {
                if (response.status === 404) {
                    setIsDeleted(true);

                    return false;
                }
            },
        });
    }

    return (
        <form onSubmit={submit}>
            <div role="alert">
                <div
                    className={cn(
                        "mb-6 flex items-start gap-3 rounded border border-red-400 bg-red-900/30 p-4 text-red-100",
                        !isDeleted && "hidden",
                    )}
                >
                    <span aria-hidden="true" className="shrink-0 pt-0.5">
                        <Icon icon="exclamation-triangle" style="solid" />
                    </span>
                    <p>{isDeleted ? DELETED_MESSAGE : ""}</p>
                </div>
            </div>

            <section
                aria-labelledby="chapter-conflict-heading"
                className={cn(
                    "mb-6 flex flex-col gap-4 rounded border border-amber-400 bg-amber-900/20 p-4",
                    !hasConflict && "hidden",
                )}
            >
                <div className="flex items-start gap-3">
                    <span aria-hidden="true" className="shrink-0 pt-1 text-amber-300">
                        <Icon icon="code-merge" style="solid" />
                    </span>
                    <div className="flex flex-col gap-1">
                        <h2 id="chapter-conflict-heading" className="font-serif text-xl text-white">
                            Someone else saved this chapter
                        </h2>
                        <p className="text-sm text-gray-300">{errors.base_hash}</p>
                    </div>
                </div>
                <div className="bg-brown-900/70 max-h-96 overflow-y-auto rounded border border-amber-600/40 px-4 py-2">
                    {hasConflict && <DiscordMarkdown content={chapter.content} mentions={mentions} />}
                </div>
                <div className="flex flex-wrap gap-3">
                    <PrimaryButton onClick={takeTheirVersion}>Use their version</PrimaryButton>
                    <SecondaryButton onClick={keepMine}>Keep mine</SecondaryButton>
                </div>
            </section>

            <div className="flex flex-col gap-6">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <div className="flex min-w-0 flex-col gap-2">
                        <h2 className="font-serif text-2xl text-white">Write</h2>
                        <p className="text-sm text-gray-300">
                            Start with the chapter's heading, such as “## 📜 Loot rules”. Chapters are posted to Discord
                            as they are, so they can be up to 2,000 characters.
                        </p>
                        <DiscordMarkdownEditor
                            value={data.content}
                            onChange={handleChange}
                            mentions={mentions}
                            onTrim={handleTrim}
                            label="Chapter content"
                        />
                        <div role="status" className="text-sm text-amber-300">
                            <p className={cn("flex items-start gap-2", trimNotice === "" && "hidden")}>
                                <span aria-hidden="true" className="shrink-0">
                                    <Icon icon="scissors" style="solid" />
                                </span>
                                <span>{trimNotice}</span>
                            </p>
                        </div>
                        <FieldError message={errors.content} />
                    </div>

                    <div className="flex min-w-0 flex-col gap-2">
                        <h2 className="font-serif text-2xl text-white">Preview</h2>
                        <p className="text-sm text-gray-300">How the chapter reads on the site.</p>
                        <div className="bg-brown-900/70 min-h-64 rounded border border-amber-600/40 px-4 py-2">
                            <DiscordMarkdown content={data.content} mentions={mentions} />
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <SaveButton
                        processing={processing}
                        label={submitLabel}
                        processingLabel={processingLabel}
                        disabled={isDeleted}
                    />
                    <Link href={route("management.raid-information.index")} className={linkClassName}>
                        Back to raid information
                    </Link>
                </div>
            </div>
        </form>
    );
}
