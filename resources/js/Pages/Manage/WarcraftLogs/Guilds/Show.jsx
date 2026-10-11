import { Button } from "@headlessui/react";
import { router, useForm } from "@inertiajs/react";
import { Can, usePermission } from "@/Components/Authorizable";
import Checkbox from "@/Components/Checkbox";
import ConfirmationModal from "@/Components/ConfirmationModal";
import DetailsForm from "@/Components/Datasets/DetailsForm";
import { focusRing } from "@/Components/Datasets/RecordCard";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import { FormRow, FormSection } from "@/Components/FormControls";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import useRecordDeletion from "@/Hooks/useRecordDeletion";
import Master from "@/Layouts/Master";

function countOf(count, singular, plural) {
    return `${count} ${count === 1 ? singular : plural}`;
}

function GuildDetails({ guild }) {
    const gameVersionTitles = guild.game_versions.map((gameVersion) => gameVersion.title).join(", ");

    return (
        <section aria-labelledby="guild-details-heading" className="flex flex-col gap-4">
            <h2 id="guild-details-heading" className="font-serif text-xl text-white">
                Details
            </h2>
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt className="text-secondary-300 text-sm">Warcraft Logs guild ID</dt>
                    <dd className="text-white">{guild.id}</dd>
                </div>
                <div>
                    <dt className="text-secondary-300 text-sm">Warcraft Logs site</dt>
                    <dd className="text-white">{guild.namespace.label}</dd>
                </div>
                <div>
                    <dt className="text-secondary-300 text-sm">Game versions</dt>
                    <dd className="text-white">{gameVersionTitles || "None yet"}</dd>
                </div>
                <div>
                    <dt className="text-secondary-300 text-sm">Reports</dt>
                    <dd className="text-white">{guild.reports_count}</dd>
                </div>
            </dl>
        </section>
    );
}

function NamespaceForm({ guild, namespaces }) {
    const form = useForm({ namespace: guild.namespace.value });

    return (
        <DetailsForm
            form={form}
            onSubmit={(visitOptions) =>
                form.patch(route("management.warcraftlogs.guilds.update", guild.id), {
                    preserveScroll: true,
                    ...visitOptions,
                })
            }
            submitLabel="Save"
            processingLabel="Saving…"
            cancelHref={route("management.warcraftlogs.guilds.index")}
        >
            {({ select }) => (
                <FormSection legend="Warcraft Logs site">
                    <FormRow
                        htmlFor="namespace"
                        label="Warcraft Logs site"
                        required
                        hint="Which Warcraft Logs site this guild's tags, reports and attendance are fetched from."
                        error={form.errors.namespace}
                        className="md:col-span-2"
                    >
                        {select("namespace", { options: namespaces, placeholder: "Choose a site", required: true })}
                    </FormRow>
                </FormSection>
            )}
        </DetailsForm>
    );
}

function GuildTagTable({ guild, hasUpdateTagsPermission }) {
    if (guild.guild_tags.length === 0) {
        return (
            <EmptyState
                icon="tags"
                message="No tags yet. Tags come from Warcraft Logs, so a new guild's tags appear once its first fetch finishes."
            />
        );
    }

    const toggleCountAttendance = (guildTag) => {
        router.patch(
            route("management.warcraftlogs.guilds.tags.toggle-attendance", [guild.id, guildTag.id]),
            { count_attendance: !guildTag.count_attendance },
            { preserveScroll: true },
        );
    };

    return (
        <table className="w-full text-left">
            <caption className="sr-only">Warcraft Logs tags of guild {guild.id}</caption>
            <thead>
                <tr className="border-ink-600/40 text-secondary-300 border-b text-sm">
                    <th scope="col" className="py-2 pr-4 font-normal">
                        Tag
                    </th>
                    <th scope="col" className="w-48 py-2 font-normal">
                        Counts toward attendance
                    </th>
                </tr>
            </thead>
            <tbody>
                {guild.guild_tags.map((guildTag) => (
                    <tr key={guildTag.id} className="border-ink-600/20 border-b last:border-b-0">
                        <td className="py-3 pr-4 text-white">{guildTag.name}</td>
                        <td className="py-3">
                            <Checkbox
                                checked={guildTag.count_attendance}
                                disabled={!hasUpdateTagsPermission}
                                onChange={() => toggleCountAttendance(guildTag)}
                                aria-label={`${guildTag.name} counts toward attendance`}
                                className={`bg-ground-800/50 h-6 w-6 disabled:cursor-not-allowed disabled:opacity-60 ${focusRing}`}
                            />
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function DeleteGuild({ guild }) {
    const deletion = useRecordDeletion();

    return (
        <section aria-labelledby="delete-guild-heading" className="border-ink-600/40 flex flex-col gap-3 border-t pt-6">
            <h2 id="delete-guild-heading" className="font-serif text-xl text-white">
                Delete this guild
            </h2>
            <p className="text-secondary-300 text-sm">
                Deleting the guild deletes its tags and detaches its game versions and reports. Nothing else is deleted,
                and recorded attendance is kept.
            </p>
            <div>
                <Button
                    onClick={() =>
                        deletion.request({
                            links: { destroy: route("management.warcraftlogs.guilds.destroy", guild.id) },
                        })
                    }
                    className="inline-flex items-center gap-1.5 rounded border border-red-400 px-3 py-1.5 text-sm text-red-300 data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-red-400 data-hover:bg-red-600/20"
                >
                    <Icon icon="trash" style="light" />
                    Delete guild {guild.id}
                </Button>
            </div>

            <ConfirmationModal
                show={!!deletion.record}
                onClose={deletion.cancel}
                onConfirm={deletion.confirm}
                title={`Delete Warcraft Logs guild ${guild.id}?`}
                confirmLabel="Delete guild"
                processingLabel="Deleting…"
                processing={deletion.deleting}
                variant="delete"
            >
                This deletes {countOf(guild.guild_tags_count, "tag", "tags")} and detaches{" "}
                {countOf(guild.game_versions_count, "game version", "game versions")} and{" "}
                {countOf(guild.reports_count, "report", "reports")} from the guild. The game versions and reports stay
                on the site, but the reports lose their tag and game version and stop syncing from Warcraft Logs until
                the guild is added again and relinked.
            </ConfirmationModal>
        </section>
    );
}

export default function Show({ guild, namespaces }) {
    const hasUpdateTagsPermission = usePermission("update-warcraft-logs-tags");
    const title = `Warcraft Logs guild ${guild.id}`;

    return (
        <Master title={title}>
            <SharedHeader backgroundClass="bg-officer-meeting" title={title} />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={route("management.warcraftlogs.guilds.index")} className={focusRing}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to Warcraft Logs guilds
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <div className="flex flex-col gap-10">
                    <GuildDetails guild={guild} />

                    <Can permission="update-warcraft-logs-guilds">
                        <NamespaceForm guild={guild} namespaces={namespaces} />
                    </Can>

                    <section aria-labelledby="guild-tags-heading" className="flex flex-col gap-4">
                        <h2 id="guild-tags-heading" className="font-serif text-xl text-white">
                            Tags
                        </h2>
                        <p className="text-secondary-300 text-sm">
                            Tags come from Warcraft Logs and can't be added or renamed here. Reports with a tag that
                            counts toward attendance are included in the attendance stats.
                        </p>
                        <GuildTagTable guild={guild} hasUpdateTagsPermission={hasUpdateTagsPermission} />
                    </section>

                    <Can permission="delete-warcraft-logs-guilds">
                        <DeleteGuild guild={guild} />
                    </Can>
                </div>
            </PageContainer>
        </Master>
    );
}
