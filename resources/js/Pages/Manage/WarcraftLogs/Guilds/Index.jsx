import { usePermission } from "@/Components/Authorizable";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import RecordCard from "@/Components/Datasets/RecordCard";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

function GuildCard({ guild }) {
    const gameVersionTitles = guild.game_versions.map((gameVersion) => gameVersion.title).join(", ");

    return (
        <RecordCard
            title={`Guild ${guild.id}`}
            subtitle={guild.namespace.label}
            details={[
                ["Game versions", gameVersionTitles || null],
                ["Tags", guild.guild_tags_count],
            ]}
            links={{ edit: route("management.warcraftlogs.guilds.show", guild.id) }}
            editLabel="Manage"
            editIcon="cog"
        />
    );
}

export default function Index({ guilds }) {
    const hasCreatePermission = usePermission("create-warcraft-logs-guilds");

    return (
        <Master title="Warcraft Logs guilds">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Warcraft Logs guilds" />

            <IndexLayout
                intro="The Warcraft Logs guilds the site fetches tags, reports and attendance from. Each game version picks one of these."
                addHref={hasCreatePermission ? route("management.warcraftlogs.guilds.create") : null}
                addLabel="Add guild"
                isEmpty={guilds.length === 0}
                emptyIcon="flag"
                emptyMessage="No Warcraft Logs guilds yet."
            >
                {guilds.map((guild) => (
                    <GuildCard key={guild.id} guild={guild} />
                ))}
            </IndexLayout>
        </Master>
    );
}
