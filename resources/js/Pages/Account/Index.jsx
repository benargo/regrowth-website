import { usePage, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";
import modelColor, { contrastingTextColor } from "@/Helpers/ModelColor";
import PlannedAbsenceRow from "@/Components/PlannedAbsences/Row";
import EmptyState from "@/Components/EmptyState";
import { Can } from "@/Components/Authorizable";
import Band from "@/Components/Band/Band";
import BandHeading from "@/Components/Band/BandHeading";
import RevokeButton from "@/Components/DesktopClients/RevokeButton";

const RELATIVE_TIME = new Intl.RelativeTimeFormat("en-GB", { numeric: "auto" });
const ABSOLUTE_TIME = new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short" });
const RELATIVE_UNITS = [
    ["year", 31536000],
    ["month", 2592000],
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
];

/** An ISO timestamp as a relative time such as "3 hours ago", or "Never" when missing. */
function timeAgoOrNever(value) {
    if (!value) {
        return "Never";
    }

    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const [unit, size] = RELATIVE_UNITS.find(([, size]) => Math.abs(seconds) >= size) ?? ["second", 1];

    return RELATIVE_TIME.format(Math.round(seconds / size), unit);
}

/** A relative `<time>` with the full date and time as its tooltip, or "Never" when missing. */
function RelativeTime({ value }) {
    if (!value) {
        return "Never";
    }

    return (
        <time dateTime={value} title={ABSOLUTE_TIME.format(new Date(value))}>
            {timeAgoOrNever(value)}
        </time>
    );
}

export default function Index() {
    const { auth, roles, planned_absences, desktop_clients } = usePage().props;
    const user = auth.user.data;

    return (
        <Master title="My Account">
            {/* TODO: bg-arcatraz is a temporary header image */}
            <SharedHeader title="My Account" backgroundClass="bg-arcatraz" />

            {/* User profile section */}
            <Band tone="light">
                <div className="container mx-auto px-4">
                    <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                        <img src={user.avatar} alt="" width={80} height={80} className="h-20 w-20 rounded-full" />
                        <div>
                            <BandHeading level={2} headingClassName="text-5xl md:text-7xl">
                                {user.display_name}
                            </BandHeading>
                            <p className="text-sm text-gray-400">@{user.username}</p>
                            {roles.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {roles.map((role) => {
                                        const roleColor = modelColor(role.primary_color, ["bg"]);

                                        return (
                                            <Pill
                                                key={role.id}
                                                bgColor={roleColor.className || undefined}
                                                style={roleColor.style}
                                                textColor={contrastingTextColor(role.primary_color)}
                                            >
                                                {role.name}
                                            </Pill>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </Band>

            {/* Planned absences section */}
            <Band tone="shaded" edge="both">
                <header className="mb-4 flex flex-col items-center justify-between md:flex-row">
                    <BandHeading level={2} eyebrow="My Account">
                        Planned Absences
                    </BandHeading>
                    <Can permission="create-planned-absences">
                        <Link
                            href={route("account.absences.create")}
                            className="mt-3 inline-flex items-center rounded-md border border-transparent bg-amber-700 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-800 focus-visible:bg-amber-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 active:bg-amber-900 md:mt-0"
                        >
                            <Icon icon="plus" style="solid" className="mr-1.5 h-4" />
                            Add Absence
                        </Link>
                    </Can>
                </header>

                {planned_absences.data.length === 0 ? (
                    <EmptyState
                        icon="calendar-times"
                        message="You haven't created any planned absences."
                        size="text-3xl"
                    />
                ) : (
                    <div className="flex flex-col gap-2">
                        {planned_absences.data.map((absence) => (
                            <PlannedAbsenceRow
                                key={absence.id}
                                absence={absence}
                                showCreatedBy
                                canEdit={Boolean(planned_absences.permissions.edit[absence.id])}
                                canDelete={Boolean(planned_absences.permissions.delete[absence.id])}
                            />
                        ))}
                    </div>
                )}
            </Band>

            {/* Desktop apps section */}
            {desktop_clients && (
                <Band tone="light">
                    <header className="mb-4 flex flex-col items-center justify-between gap-3 md:flex-row">
                        <BandHeading level={2} eyebrow="My Account">
                            Desktop Apps
                        </BandHeading>
                        {desktop_clients.data.length > 0 && (
                            <RevokeButton
                                href={route("account.desktop-clients.destroy-all")}
                                label="Sign out of all desktop apps"
                                prompt="Sign out every linked desktop app? Each one will need linking again."
                                confirmLabel="Sign out of all"
                            />
                        )}
                    </header>

                    {desktop_clients.data.length === 0 ? (
                        <EmptyState icon="desktop" message="You have no linked desktop apps." size="text-3xl" />
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {desktop_clients.data.map((client) => (
                                <li
                                    key={client.id}
                                    className="bg-brown-800/50 flex flex-col gap-2 rounded border border-amber-800/50 px-4 py-3 sm:flex-row sm:items-center sm:gap-4"
                                >
                                    <div className="flex min-w-0 flex-1 items-center gap-2 font-medium text-amber-300">
                                        <Icon icon="desktop" style="regular" className="h-4 shrink-0" />
                                        <span className="min-w-0 break-words">{client.name}</span>
                                    </div>
                                    <p className="text-sm text-gray-400">
                                        Last seen <RelativeTime value={client.lastSeenAt} />
                                        <span className="sr-only">,</span>
                                        <span className="mx-2" aria-hidden="true">
                                            /
                                        </span>
                                        Last upload <RelativeTime value={client.lastUploadAt} />
                                    </p>
                                    <div className="shrink-0">
                                        <RevokeButton
                                            href={route("account.desktop-clients.destroy", client.id)}
                                            label="Sign out"
                                            ariaLabel={`Sign out ${client.name}`}
                                            prompt={`Sign out ${client.name}?`}
                                            confirmLabel="Sign out"
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Band>
            )}
        </Master>
    );
}
