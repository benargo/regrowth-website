import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import MetaCard, { MetaItem } from "@/Components/MetaCard";
import Pill from "@/Components/Pill";
import Icon from "@/Components/FontAwesome/Icon";
import { Can } from "@/Components/Authorizable";
import SpecIcon from "@/Components/Characters/SpecIcon";
import SpecBadge from "@/Components/Characters/SpecBadge";
import EmptyState from "@/Components/EmptyState";
import ToolNav from "@/Components/ToolNav";
import raidSpec from "@/Helpers/RaidSpec";
import useCharacterPortraitChannel from "@/Hooks/useCharacterPortraitChannel";

const RANK_COLORS = {
    "guild-master": { bg: "bg-red-900/40", text: "text-red-300", border: "border-red-700/50" },
    "officer":      { bg: "bg-red-900/40", text: "text-red-300", border: "border-red-700/50" },
    "raider":       { bg: "bg-orange-900/40", text: "text-orange-300", border: "border-orange-700/50" },
    "trial-raider": { bg: "bg-orange-900/30", text: "text-orange-400", border: "border-orange-700/40" },
    "warden":       { bg: "bg-green-900/40", text: "text-green-300", border: "border-green-700/50" },
    "champion":     { bg: "bg-green-900/40", text: "text-green-300", border: "border-green-700/50" },
    "veteran":      { bg: "bg-green-900/40", text: "text-green-300", border: "border-green-700/50" },
    "member":       { bg: "bg-green-900/40", text: "text-green-300", border: "border-green-700/50" },
    "initiate":     { bg: "bg-green-900/30", text: "text-green-400", border: "border-green-700/40" },
    "inactive":     { bg: "bg-gray-800/50", text: "text-gray-400", border: "border-gray-600/40" },
};

function rankSlug(name) {
    return name?.toLowerCase().replace(/\s+/g, "-").replace(/[^a-z0-9-]/g, "") ?? "";
}

function RankPill({ rank }) {
    if (!rank?.name) {
        return null;
    }
    const slug = rankSlug(rank.name);
    const colors = RANK_COLORS[slug] ?? { bg: "bg-gray-800/50", text: "text-gray-400", border: "border-gray-600/40" };

    return (
        <span className={`inline-flex items-center rounded border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide ${colors.bg} ${colors.text} ${colors.border}`}>
            {rank.name}
        </span>
    );
}

function ReportsSkeleton() {
    return (
        <div className="animate-pulse space-y-2">
            {[...Array(5)].map((_, i) => (
                <div key={i} className="h-12 rounded bg-brown-800/50" />
            ))}
        </div>
    );
}

function ReportRow({ report }) {
    const date = new Date(report.start_time).toLocaleDateString(undefined, {
        day: "numeric",
        month: "short",
        year: "numeric",
    });

    return (
        <Link
            href={route("raiding.reports.show", report.id)}
            className="flex items-center justify-between rounded border border-brown-700 bg-brown-800/40 px-4 py-3 transition-colors hover:border-amber-600/40 hover:bg-brown-800"
        >
            <span className="font-medium text-white">{report.title}</span>
            <span className="text-sm text-gray-500">{date}</span>
        </Link>
    );
}

function SectionHeading({ children }) {
    return (
        <h2 className="mb-4 flex items-center gap-3 text-xs font-bold uppercase tracking-[0.15em] text-amber-500/70">
            <span>{children}</span>
            <span className="h-px flex-1 bg-amber-600/20" />
        </h2>
    );
}

export default function Show({ character, recent_reports }) {
    const spec = raidSpec(character);
    const isLoading = recent_reports === undefined;

    useCharacterPortraitChannel(character.id, () => {
        router.reload({ only: ["character"] });
    });

    return (
        <Master title={character.name}>
            <SharedHeader backgroundClass="bg-stormwind" title={character.name} />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <Link
                        href={route("characters.index")}
                        className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-2" />
                        <span>Roster</span>
                    </Link>
                </div>
            </ToolNav>

            <PageContainer>
                {/* Identity strip */}
                <div className="mb-8 flex flex-wrap items-center gap-4">
                    {character.portrait_url ? (
                        <img
                            src={character.portrait_url}
                            alt={character.name}
                            className="h-20 w-20 rounded-xl border border-amber-600/30 shadow-lg shadow-black/50"
                        />
                    ) : (
                        <div className="flex h-20 w-20 items-center justify-center rounded-xl border border-gray-600/30 bg-gray-700/50 shadow-lg shadow-black/50">
                            {character.playable_class?.icon_url ? (
                                <img
                                    src={character.playable_class.icon_url}
                                    alt={character.playable_class.name}
                                    className="h-10 w-10 rounded opacity-40"
                                />
                            ) : (
                                <Icon icon="user" style="light" className="text-2xl text-gray-500" />
                            )}
                        </div>
                    )}
                    <div>
                        <h2 className="text-2xl font-bold text-white">{character.name}</h2>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            {character.is_main && (
                                <Pill bgColor="bg-amber-700" textColor="text-amber-200">Main</Pill>
                            )}
                            {character.is_loot_councillor && (
                                <Pill bgColor="bg-purple-800" textColor="text-purple-200">Loot Council</Pill>
                            )}
                        </div>
                    </div>

                    <Can permission="update-characters">
                        <Link
                            href={route("characters.edit", {
                                character: character.id,
                                slug: character.slug,
                            })}
                            className="ml-auto flex items-center gap-2 rounded border border-amber-600/60 px-4 py-2 text-sm font-medium text-amber-400 transition-colors hover:bg-amber-600/20"
                        >
                            <Icon icon="edit" style="light" />
                            Edit Character
                        </Link>
                    </Can>
                </div>

                {/* Meta card */}
                <MetaCard>
                    <MetaItem>
                        <span className="inline-flex items-center gap-1.5">
                            {character.playable_class?.icon_url && (
                                <img
                                    src={character.playable_class.icon_url}
                                    alt={character.playable_class.name}
                                    className="h-4 w-4 rounded"
                                />
                            )}
                            Level {character.level}
                            {character.playable_race?.name ? ` · ${character.playable_race.name}` : ""}
                            {character.playable_class?.name ? ` · ${character.playable_class.name}` : ""}
                        </span>
                    </MetaItem>
                    {spec && (
                        <MetaItem icon="star">
                            <span className="inline-flex items-center gap-1.5">
                                <SpecIcon specialization={spec} playableClass={character.playable_class} size={4} />
                                {spec.name}
                                <span className="text-xs text-gray-500">raid spec</span>
                            </span>
                        </MetaItem>
                    )}
                    {character.rank?.name && (
                        <MetaItem>
                            <RankPill rank={character.rank} />
                        </MetaItem>
                    )}
                </MetaCard>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Left column: specs + alts */}
                    <div className="space-y-8 lg:col-span-1">
                        {/* Specializations */}
                        {character.specializations && character.specializations.length > 0 && (
                            <section>
                                <SectionHeading>Specializations</SectionHeading>
                                <div className="flex flex-wrap gap-2">
                                    {character.specializations.map((spec) => (
                                        <SpecBadge
                                            key={spec.id}
                                            spec={spec}
                                            isRaid={!!spec.is_raid_spec}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}

                        {/* Linked characters (alts) */}
                        <section>
                            <SectionHeading>Linked Characters</SectionHeading>
                            {character.linked_characters && character.linked_characters.length > 0 ? (
                                <div className="space-y-2">
                                    {character.linked_characters.map((alt) => (
                                        <Link
                                            key={alt.id}
                                            href={route("characters.show", {
                                                character: alt.id,
                                                slug: alt.slug,
                                            })}
                                            className="flex items-center gap-3 rounded border border-brown-700 bg-brown-800/40 px-3 py-2.5 transition-colors hover:border-amber-600/40 hover:bg-brown-800"
                                        >
                                            {alt.playable_class?.icon_url && (
                                                <img
                                                    src={alt.playable_class.icon_url}
                                                    alt={alt.playable_class.name}
                                                    className="h-6 w-6 rounded"
                                                />
                                            )}
                                            <span className="flex-1 font-medium text-white">{alt.name}</span>
                                            <span className="text-xs text-gray-500">{alt.rank?.name ?? "—"}</span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <EmptyState
                                    icon="unlink"
                                    iconStyle="light"
                                    size="text-2xl"
                                    message="No linked characters found."
                                >
                                    <p className="text-xs text-gray-600">If this is an error, please alert an Officer.</p>
                                </EmptyState>
                            )}
                        </section>
                    </div>

                    {/* Right column: recent reports */}
                    <div className="lg:col-span-2">
                        <SectionHeading>Recent raids</SectionHeading>
                        {isLoading ? (
                            <ReportsSkeleton />
                        ) : recent_reports?.length > 0 ? (
                            <div className="space-y-2">
                                {recent_reports.map((report) => (
                                    <ReportRow key={report.id} report={report} />
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No reports found for this character.</p>
                        )}
                    </div>
                </div>
            </PageContainer>
        </Master>
    );
}
