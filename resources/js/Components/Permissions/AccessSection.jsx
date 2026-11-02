import { useId } from "react";
import Switch from "@/Components/Switch";
import { LINK_CLASS, NUMBER_FORMAT } from "@/Components/Permissions/PermissionsPage";
import cn from "@/Helpers/cn";

/**
 * One section of the Access tab: a `title` heading, an "X of Y on" count for `role`, a "Turn all on" /
 * "Turn all off" button, and a row per permission with its label, description and a switch.
 * A switch whose id is in `busyIds` is busy; while any of the section's ids are busy the "Turn all"
 * button is `aria-disabled` and ignores clicks. `onToggle(permission)` and `onToggleAll()` fire on
 * a switch flip and on "Turn all".
 */
export default function AccessSection({ title, permissions, role, busyIds, onToggle, onToggleAll }) {
    const sectionId = useId();
    const headingId = `${sectionId}-heading`;

    const enabledCount = permissions.filter((permission) => permission.discord_role_ids.includes(role.id)).length;
    const isAllOn = enabledCount === permissions.length;
    const isSectionBusy = permissions.some((permission) => busyIds.has(permission.id));
    const toggleAllText = isAllOn ? "Turn all off" : "Turn all on";

    return (
        <section aria-labelledby={headingId}>
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 border-b border-amber-600 pb-1">
                <h3 id={headingId} className="text-lg font-semibold text-white">
                    {title}
                </h3>
                <div className="flex items-baseline gap-4">
                    <span className="text-sm text-gray-400 tabular-nums">
                        {NUMBER_FORMAT.format(enabledCount)} of {NUMBER_FORMAT.format(permissions.length)} on
                    </span>
                    <button
                        type="button"
                        aria-label={`${toggleAllText}: ${title}`}
                        aria-disabled={isSectionBusy}
                        onClick={() => {
                            if (!isSectionBusy) {
                                onToggleAll();
                            }
                        }}
                        className={cn(
                            LINK_CLASS,
                            "min-h-11 cursor-pointer underline md:min-h-0",
                            isSectionBusy && "cursor-wait opacity-50",
                        )}
                    >
                        {toggleAllText}
                    </button>
                </div>
            </div>

            <div className="divide-y divide-amber-600/25">
                {permissions.map((permission) => {
                    const descriptionId = permission.description
                        ? `${sectionId}-${permission.id}-description`
                        : undefined;

                    return (
                        <label
                            key={permission.id}
                            className="flex min-h-11 cursor-pointer items-center justify-between gap-4 py-2.5 hover:bg-amber-600/10"
                        >
                            <span className="min-w-0">
                                <span className="block text-stone-200">{permission.label}</span>
                                {permission.description && (
                                    <span id={descriptionId} className="block text-sm text-gray-400">
                                        {permission.description}
                                    </span>
                                )}
                            </span>
                            <Switch
                                checked={permission.discord_role_ids.includes(role.id)}
                                isBusy={busyIds.has(permission.id)}
                                onChange={() => onToggle(permission)}
                                label={`${role.name}: ${permission.label}`}
                                describedBy={descriptionId}
                            />
                        </label>
                    );
                })}
            </div>
        </section>
    );
}
