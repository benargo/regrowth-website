import { Input } from "@headlessui/react";
import DetailsForm from "@/Components/Datasets/DetailsForm";
import { FormRow, FormSection, controlClassName } from "@/Components/FormControls";

const FIELD_LABELS = {
    title: "Title",
    slug: "Slug",
    release_date: "Release date",
    theme: "Theme",
    realm: "Realm",
    guild_name: "Guild name",
    faction: "Faction",
    blizzard_namespace: "Blizzard API namespace",
    warcraftlogs_guild: "Warcraft Logs guild ID",
    warcraftlogs_namespace: "Warcraft Logs namespace",
};

/**
 * Build the initial useForm state for a game version.
 */
export function gameVersionFormData(gameVersion = null) {
    return {
        title: gameVersion?.title ?? "",
        ...(gameVersion === null && { slug: "" }),
        realm: gameVersion?.realm ?? "",
        guild_name: gameVersion?.guild_name ?? "",
        faction: gameVersion?.faction ?? "",
        release_date: gameVersion?.release_date ?? "",
        theme: gameVersion?.theme ?? "",
        blizzard_namespace: gameVersion?.blizzard?.namespace ?? "",
        warcraftlogs_guild: gameVersion?.warcraftlogs?.guild ?? "",
        warcraftlogs_namespace: gameVersion?.warcraftlogs?.namespace?.value ?? "",
    };
}

/**
 * The game version details form, for creating or editing (`gameVersion`).
 * Props other than `options` pass through to DetailsForm.
 */
export default function GameVersionForm({ form, options, gameVersion = null, ...detailsFormProps }) {
    const { errors } = form;

    return (
        <DetailsForm form={form} {...detailsFormProps}>
            {({ text, slug, select }) => (
                <>
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
                        {gameVersion === null ? (
                            <FormRow
                                htmlFor="slug"
                                label={FIELD_LABELS.slug}
                                required
                                hint="A short name for links, such as tbc. Aim for 16 characters or fewer. It can't be changed later."
                                error={errors.slug}
                            >
                                {slug("slug", { required: true, maxLength: 255 })}
                            </FormRow>
                        ) : (
                            <FormRow
                                htmlFor="slug"
                                label={FIELD_LABELS.slug}
                                hint="Set when the game version was created. It can't be changed."
                            >
                                <Input id="slug" value={gameVersion.slug} disabled className={controlClassName} />
                            </FormRow>
                        )}
                        <FormRow
                            htmlFor="release_date"
                            label={FIELD_LABELS.release_date}
                            required
                            error={errors.release_date}
                        >
                            {text("release_date", { type: "date", required: true })}
                        </FormRow>
                        <FormRow
                            htmlFor="theme"
                            label={FIELD_LABELS.theme}
                            required
                            hint="Sets the site's colours and banners while this version is active."
                            error={errors.theme}
                        >
                            {select("theme", {
                                options: options.themes,
                                placeholder: "Choose a theme",
                                required: true,
                            })}
                        </FormRow>
                    </FormSection>

                    <FormSection legend="Where the guild plays">
                        <FormRow htmlFor="realm" label={FIELD_LABELS.realm} error={errors.realm}>
                            {text("realm", { autoComplete: "off" })}
                        </FormRow>
                        <FormRow
                            htmlFor="guild_name"
                            label={FIELD_LABELS.guild_name}
                            required
                            error={errors.guild_name}
                        >
                            {text("guild_name", { required: true, maxLength: 24, autoComplete: "off" })}
                        </FormRow>
                        <FormRow htmlFor="faction" label={FIELD_LABELS.faction} error={errors.faction}>
                            {select("faction", { options: options.factions, placeholder: "Not set" })}
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
                            {select("blizzard_namespace", {
                                options: options.blizzard_namespaces,
                                placeholder: "Not set",
                            })}
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
                            {select("warcraftlogs_namespace", {
                                options: options.warcraftlogs_namespaces,
                                placeholder: "Not set",
                            })}
                        </FormRow>
                    </FormSection>
                </>
            )}
        </DetailsForm>
    );
}
