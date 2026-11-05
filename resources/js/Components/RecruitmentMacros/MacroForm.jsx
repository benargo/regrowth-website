import { Link, useForm } from "@inertiajs/react";
import DiscordMarkdown from "@/Components/Discord/DiscordMarkdown";
import DiscordMarkdownEditor from "@/Components/Discord/DiscordMarkdownEditor";
import {
    FieldError,
    FormRow,
    OptionSelect,
    SaveButton,
    TextInput,
    controlClassName,
    linkClassName,
} from "@/Components/FormControls";
import cn from "@/Helpers/cn";

const DISCORD = "discord";

/**
 * Counts the content in the unit the type's limit uses: UTF-16 units for
 * Discord, characters for in-game chat.
 *
 * @param {string} content
 * @param {string} type
 * @returns {number}
 */
function countCharacters(content, type) {
    return type === DISCORD ? content.length : Array.from(content).length;
}

/**
 * A recruitment macro's title, type and content. In-game macros use a plain
 * textarea; Discord macros use the Discord markdown editor beside a preview.
 * With a `macro` it saves that macro, otherwise it adds a new one.
 *
 * @param {Object} props
 * @param {Object|null} [props.macro] - The macro being edited, or null to add one
 * @param {Array<{ value: string, label: string, character_limit: number }>} props.types - The macro types
 * @param {{ channels: Array, roles: Array, emoji: Array }} props.mentions - The mention directory
 * @param {string} props.submitLabel - The save button's label
 * @param {string} props.processingLabel - The save button's label while saving
 */
export default function MacroForm({ macro = null, types, mentions, submitLabel, processingLabel }) {
    const isEditing = macro !== null;
    const { data, setData, errors, clearErrors, processing, post, patch } = useForm({
        title: macro?.title ?? "",
        type: macro?.type ?? types[0].value,
        content: macro?.content ?? "",
    });
    const selectedType = types.find((type) => type.value === data.type) ?? types[0];
    const characterCount = countCharacters(data.content, data.type);
    const isOverLimit = characterCount > selectedType.character_limit;

    function submit(event) {
        event.preventDefault();

        if (isEditing) {
            patch(route("management.recruitment-macros.update", macro.id), { preserveScroll: true });

            return;
        }

        post(route("management.recruitment-macros.store"), { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <FormRow label="Title" required error={errors.title}>
                    <TextInput
                        name="title"
                        value={data.title}
                        onChange={(event) => setData("title", event.target.value)}
                        maxLength={100}
                        invalid={Boolean(errors.title)}
                        required
                    />
                </FormRow>
                <FormRow htmlFor="type" label="Posted in" required error={errors.type}>
                    <OptionSelect
                        name="type"
                        value={data.type}
                        onChange={(value) => {
                            setData("type", value);
                            clearErrors("content");
                        }}
                        options={types}
                        invalid={Boolean(errors.type)}
                        required
                    />
                </FormRow>
            </div>

            {data.type === DISCORD ? (
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <div className="flex min-w-0 flex-col gap-2">
                        <h2 className="font-serif text-2xl text-white">Write</h2>
                        <DiscordMarkdownEditor
                            value={data.content}
                            onChange={(content) => setData("content", content)}
                            mentions={mentions}
                            label="Macro content"
                        />
                        <FieldError message={errors.content} />
                    </div>
                    <div className="flex min-w-0 flex-col gap-2">
                        <h2 className="font-serif text-2xl text-white">Preview</h2>
                        <div className="bg-brown-900/70 min-h-64 rounded border border-amber-600/40 px-4 py-2 wrap-anywhere">
                            <DiscordMarkdown content={data.content} mentions={mentions} />
                        </div>
                    </div>
                </div>
            ) : (
                <>
                    <FormRow
                        htmlFor="content"
                        label="Macro content"
                        required
                        hint="Plain text, pasted into a chat channel in game."
                        error={errors.content}
                    >
                        <textarea
                            id="content"
                            name="content"
                            rows={4}
                            value={data.content}
                            onChange={(event) => setData("content", event.target.value)}
                            aria-invalid={isOverLimit || Boolean(errors.content) ? true : undefined}
                            aria-describedby="macro-character-count"
                            className={cn(controlClassName, "font-mono")}
                            required
                        />
                    </FormRow>
                    <p
                        id="macro-character-count"
                        role="status"
                        className={cn("text-sm tabular-nums", isOverLimit ? "text-red-300" : "text-gray-300")}
                    >
                        {characterCount.toLocaleString("en-GB")} /{" "}
                        {selectedType.character_limit.toLocaleString("en-GB")} characters
                        {isOverLimit && " — too long for " + selectedType.label}
                    </p>
                </>
            )}

            <div className="flex flex-wrap items-center gap-3">
                <SaveButton processing={processing} label={submitLabel} processingLabel={processingLabel} />
                <Link href={route("management.recruitment-macros.index")} className={linkClassName}>
                    Back to recruitment macros
                </Link>
            </div>
        </form>
    );
}
