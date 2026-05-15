import { Link } from "@inertiajs/react";
import Collapsible from "@/Components/Collapsible";
import Icon from "@/Components/FontAwesome/Icon";
import AssignmentGroup from "@/Components/Events/Assignments";
import { BenchedTable, GroupTable } from "@/Components/Events/GroupTable";
import MetaCard, { MetaItem } from "@/Components/MetaCard";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import SharedHeader from "@/Components/SharedHeader";
import formatDate from "@/Helpers/FormatDate";
import formatDuration from "@/Helpers/FormatDuration";
import usePermission from "@/Hooks/Permissions.jsx";
import Master from "@/Layouts/Master";

export default function Show({ event }) {
    const startDate = new Date(event.start_time);
    const endDate = new Date(event.end_time);
    const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
    const formattedDate = formatDate(event.start_time);
    const duration = formatDuration({ seconds: event.duration });
    const formatTime = (date) => date.toLocaleString("en-GB", { hour: "2-digit", minute: "2-digit" });

    const groups = event.composition?.groups ?? [];
    const bench = event.composition?.bench ?? [];

    return (
        <Master title={event.title}>
            <SharedHeader title={event.title} backgroundClass={event.background} />

            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Event metadata card */}
                    <MetaCard>
                        <MetaItem icon="calendar">
                            <span>
                                {dayOfWeek}, <span className="md:hidden">{formattedDate.short}</span>
                                <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                <span className="hidden lg:inline">{formattedDate.long}</span>
                            </span>
                        </MetaItem>
                        <MetaItem icon="clock">
                            {formatTime(startDate)}–{formatTime(endDate)} ({duration})
                        </MetaItem>
                        {event.raids.length > 0 && (
                            <MetaItem icon="shield-alt">{event.raids.map((raid) => raid.name).join(", ")}</MetaItem>
                        )}
                        <div className="grow" />
                        {usePermission("manage-raid-plans") && (
                            <Link
                                href={route("raiding.plans.edit", event.id)}
                                className="inline-flex items-center gap-2 rounded border border-amber-600 px-4 py-2 text-sm text-gray-200 transition-colors hover:bg-amber-600/20"
                            >
                                <Icon icon="pencil" />
                                Edit
                            </Link>
                        )}
                    </MetaCard>

                    {groups.length > 0 ? (
                        <div className="mb-12 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {groups.map((group) => (
                                <GroupTable key={group.group_number} group={group} />
                            ))}
                            {bench.length > 0 && <BenchedTable characters={bench} />}
                        </div>
                    ) : (
                        <p className="flex-1 text-center text-sm text-gray-400">
                            Groups for this raid have not been posted yet.
                        </p>
                    )}

                    {/** General assignments */}
                    {event.assignments.count > 0 && (
                        <div className="mb-8">
                            <h2 className="text-md mb-2 font-semibold uppercase tracking-wider text-amber-500/80">
                                General Assignments
                            </h2>
                            <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                                {event.assignments.groups.length > 0 &&
                                    event.assignments.groups.map((group) => (
                                        <AssignmentGroup key={group.id} group={group} />
                                    ))}
                                {event.assignments.ungrouped.length > 0 && (
                                    <AssignmentGroup assignments={event.assignments.ungrouped} />
                                )}
                            </div>
                        </div>
                    )}

                    {/* Raids and bosses */}
                    {event.raids.length > 0 && (
                        <div className="mt-8 space-y-8">
                            {event.raids.map((raid) => (
                                <div key={raid.slug}>
                                    {event.raids.length > 1 && (
                                        <h2 className="mb-4 text-xl font-semibold text-white">{raid.name}</h2>
                                    )}
                                    <div className="flex flex-col gap-2">
                                        {(raid.bosses ?? []).map((boss) => (
                                            <Collapsible
                                                key={boss.id}
                                                title={boss.name}
                                                sessionKey={`event_boss_expanded_${raid.slug}_${boss.id}`}
                                                className="border-amber-600"
                                                headerClassName="hover:bg-amber-600/10"
                                                bodyClassName="border-amber-600"
                                            >
                                                <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                                                    <div className="flex flex-col items-center gap-2 text-center">
                                                        {boss.assignments?.count > 0 ? (
                                                            <div className="flex w-full flex-col items-start gap-4">
                                                                <h2 className="flex-1 text-lg font-semibold text-amber-500">
                                                                    Assignments
                                                                </h2>
                                                                {boss.assignments.groups.length > 0 &&
                                                                    boss.assignments.groups.map((group) => (
                                                                        <AssignmentGroup key={group.id} group={group} />
                                                                    ))}
                                                                {boss.assignments.ungrouped.length > 0 && (
                                                                    <AssignmentGroup
                                                                        assignments={boss.assignments.ungrouped}
                                                                    />
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <p className="text-center text-sm text-gray-500">
                                                                No assignments for this boss yet.
                                                            </p>
                                                        )}
                                                    </div>
                                                    {boss.images?.length > 0 || boss.notes ? (
                                                        <div className="col-span-2 flex flex-col gap-4">
                                                            <div className="flex flex-row items-start gap-2">
                                                                <h2 className="flex-1 text-lg font-semibold text-amber-500">
                                                                    Strategy
                                                                </h2>
                                                                {usePermission("manage-boss-strategies") && (
                                                                    <Link
                                                                        href={route("dashboard.boss-strategies.edit", {
                                                                            boss: boss.id,
                                                                            slug: boss.slug,
                                                                        })}
                                                                        className="inline-flex items-center gap-2 rounded border border-amber-600 px-4 py-2 text-sm text-gray-200 transition-colors hover:bg-amber-600/20"
                                                                    >
                                                                        <Icon icon="pencil" className="text-sm" />
                                                                        Edit boss strategy
                                                                    </Link>
                                                                )}
                                                            </div>
                                                            {boss.images?.length > 0 &&
                                                                boss.images.map((url, i) => (
                                                                    <div
                                                                        key={`${boss.id}_image_${i}`}
                                                                        className="flex items-center justify-center gap-4 text-center"
                                                                    >
                                                                        <img
                                                                            key={i}
                                                                            src={url}
                                                                            alt={`${boss.name} strategy ${i + 1}`}
                                                                            className="rounded-lg border border-amber-600/30"
                                                                        />
                                                                    </div>
                                                                ))}
                                                            {boss.notes && (
                                                                <FormattedMarkdown>{boss.notes}</FormattedMarkdown>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <div className="col-span-2 flex items-center justify-center gap-4 text-center">
                                                            <p className="text-center text-sm text-gray-500">
                                                                No strategy notes or images for this boss yet.
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            </Collapsible>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </Master>
    );
}
