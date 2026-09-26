import { useEffect, useRef, useState } from "react";
import { Link } from "@inertiajs/react";
import { Button, Input } from "@headlessui/react";
import {
    ErrorSummary,
    FormRow,
    FormSection,
    OptionSelect,
    RequiredFieldsNote,
    buttonClassName,
    controlClassName,
    linkClassName,
} from "@/Components/FormControls";
import { useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

const FIELD_LABELS = {
    title: "Title",
    release_date: "Release date",
    theme: "Theme",
    realm: "Realm",
    faction: "Faction",
    blizzard_namespace: "Blizzard API namespace",
    warcraftlogs_guild: "Warcraft Logs guild ID",
    warcraftlogs_namespace: "Warcraft Logs namespace",
};

/**
 * Build the initial useForm state for a game version. Blank values are
 * empty strings so inputs stay controlled; Laravel converts them to null.
 */
export function gameVersionFormData(gameVersion = null) {
    return {
        title: gameVersion?.title ?? "",
        realm: gameVersion?.realm ?? "",
        faction: gameVersion?.faction ?? "",
        release_date: gameVersion?.release_date ?? "",
        theme: gameVersion?.theme ?? "",
        blizzard_namespace: gameVersion?.blizzard?.namespace ?? "",
        warcraftlogs_guild: gameVersion?.warcraftlogs?.guild ?? "",
        warcraftlogs_namespace: gameVersion?.warcraftlogs?.namespace?.value ?? "",
    };
}

/**
 * The game version details form. With `autosave` ({ url, saved }) it saves
 * the changed fields once focus leaves the form, and has no submit button.
 * Without it, it submits through `onSubmit`, as on the Create page.
 */
export default function GameVersionForm({ form, options, onSubmit, submitLabel, processingLabel, autosave }) {
    const { data, setData, processing, errors } = form;
    const summaryRef = useRef(null);
    const [failedSubmits, setFailedSubmits] = useState(0);
    const { schedule, flush, containerProps } = useFormAutosave({
        form,
        url: autosave?.url,
        saved: autosave?.saved ?? {},
        key: "details",
        trigger: "blur",
        delay: AUTOSAVE_DELAY,
        enabled: Boolean(autosave),
    });

    // Focus after React commits the errors render, so the summary exists.
    useEffect(() => {
        if (failedSubmits > 0) {
            summaryRef.current?.focus();
        }
    }, [failedSubmits]);

    function handleSubmit(e) {
        e.preventDefault();

        // Pressing Enter saves straight away. Autosave failures don't move
        // focus to the summary: the page's status line announces them.
        if (autosave) {
            flush();
            return;
        }

        onSubmit({ onError: () => setFailedSubmits((count) => count + 1) });
    }

    function select(name, value) {
        setData(name, value);
        schedule();
    }

    const text = (name, props = {}) => (
        <Input
            id={name}
            name={name}
            value={data[name]}
            onChange={(e) => setData(name, e.target.value)}
            invalid={!!errors[name]}
            className={controlClassName}
            {...props}
        />
    );

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8" {...containerProps}>
            <ErrorSummary errors={errors} summaryRef={summaryRef} />

            <RequiredFieldsNote />

            <FormSection legend="The version">
                <FormRow
                    htmlFor="title"
                    label={FIELD_LABELS.title}
                    required
                    error={errors.title}
                    className="md:col-span-2"
                >
                    {text("title", { required: true, autoComplete: "off" })}
                </FormRow>
                <FormRow htmlFor="release_date" label={FIELD_LABELS.release_date} required error={errors.release_date}>
                    {text("release_date", { type: "date", required: true })}
                </FormRow>
                <FormRow
                    htmlFor="theme"
                    label={FIELD_LABELS.theme}
                    required
                    hint="Sets the site's colours and banners while this version is active."
                    error={errors.theme}
                >
                    <OptionSelect
                        name="theme"
                        value={data.theme}
                        onChange={(value) => select("theme", value)}
                        options={options.themes}
                        placeholder="Choose a theme"
                        invalid={!!errors.theme}
                        required
                    />
                </FormRow>
            </FormSection>

            <FormSection legend="Where the guild plays">
                <FormRow htmlFor="realm" label={FIELD_LABELS.realm} error={errors.realm}>
                    {text("realm", { autoComplete: "off" })}
                </FormRow>
                <FormRow htmlFor="faction" label={FIELD_LABELS.faction} error={errors.faction}>
                    <OptionSelect
                        name="faction"
                        value={data.faction}
                        onChange={(value) => select("faction", value)}
                        options={options.factions}
                        placeholder="Not set"
                        invalid={!!errors.faction}
                    />
                </FormRow>
            </FormSection>

            <FormSection legend="Integrations">
                <FormRow
                    htmlFor="blizzard_namespace"
                    label={FIELD_LABELS.blizzard_namespace}
                    hint="Which Blizzard API data set items and media are fetched from."
                    error={errors.blizzard_namespace}
                    className="md:col-span-2"
                >
                    <OptionSelect
                        name="blizzard_namespace"
                        value={data.blizzard_namespace}
                        onChange={(value) => select("blizzard_namespace", value)}
                        options={options.blizzard_namespaces}
                        placeholder="Not set"
                        invalid={!!errors.blizzard_namespace}
                    />
                </FormRow>
                <FormRow
                    htmlFor="warcraftlogs_guild"
                    label={FIELD_LABELS.warcraftlogs_guild}
                    hint="The number at the end of the guild's Warcraft Logs page address."
                    error={errors.warcraftlogs_guild}
                >
                    {text("warcraftlogs_guild", { type: "number", min: 1, inputMode: "numeric" })}
                </FormRow>
                <FormRow
                    htmlFor="warcraftlogs_namespace"
                    label={FIELD_LABELS.warcraftlogs_namespace}
                    hint="Which Warcraft Logs site this version's reports come from."
                    error={errors.warcraftlogs_namespace}
                >
                    <OptionSelect
                        name="warcraftlogs_namespace"
                        value={data.warcraftlogs_namespace}
                        onChange={(value) => select("warcraftlogs_namespace", value)}
                        options={options.warcraftlogs_namespaces}
                        placeholder="Not set"
                        invalid={!!errors.warcraftlogs_namespace}
                    />
                </FormRow>
            </FormSection>

            {!autosave && (
                <div className="flex flex-wrap items-center gap-4">
                    <Button type="submit" disabled={processing} className={buttonClassName}>
                        {processing ? processingLabel : submitLabel}
                    </Button>
                    <Link href={route("management.game-versions.index")} className={linkClassName}>
                        Cancel
                    </Link>
                </div>
            )}
        </form>
    );
}
