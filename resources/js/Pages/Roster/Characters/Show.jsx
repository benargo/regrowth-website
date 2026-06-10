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
import raidSpec from "@/Helpers/RaidSpec";
import useCharacterPortraitChannel from "@/Hooks/useCharacterPortraitChannel";

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

            <PageContainer>
                {/* Identity strip */}
                <div className="mb-8 flex flex-wrap items-center gap-3">
                    {character.portrait_url ? (
                        <img
                            src={character.portrait_url}
                            alt={character.name}
                            className="h-12 w-12 rounded-lg border border-amber-600/30 shadow-lg shadow-black/40"
                        />
                    ) : (
                        <div
                            className="h-12 w-12 rounded-lg bg-gray-600 border border-gray-600/30 shadow-lg shadow-black/40"
                        />
                    )}
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-2xl font-bold text-white">{character.name}</h2>
                            {character.is_main && (
                                <Pill bgColor="bg-amber-700" textColor="text-amber-200">Main</Pill>
                            )}
                            {character.is_loot_councillor && (
                                <Pill bgColor="bg-purple-800" textColor="text-purple-200">Loot Council</Pill>
                            )}
                        </div>
                        <p className="text-sm text-gray-400">
                            Level {character.level}
                            {character.playable_race?.name ? ` · ${character.playable_race.name}` : ""}
                            {character.playable_class?.name ? ` · ${character.playable_class.name}` : ""}
                        </p>
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
                    <MetaItem icon="shield-alt">{character.rank?.name ?? "—"}</MetaItem>
                    {spec && (
                        <MetaItem icon="star">
                            <span className="inline-flex items-center gap-1.5">
                                <SpecIcon specialization={spec} playableClass={character.playable_class} size={4} />
                                {spec.name}
                                <span className="text-gray-500 text-xs">raid spec</span>
                            </span>
                        </MetaItem>
                    )}
                    {character.playable_class?.name && (
                        <MetaItem icon="chess-rook">{character.playable_class.name}</MetaItem>
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
                        {character.linked_characters && character.linked_characters.length > 0 && (
                            <section>
                                <SectionHeading>Linked Characters</SectionHeading>
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
                            </section>
                        )}
                    </div>

                    {/* Right column: recent reports */}
                    <div className="lg:col-span-2">
                        <SectionHeading>Recent Reports</SectionHeading>
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
