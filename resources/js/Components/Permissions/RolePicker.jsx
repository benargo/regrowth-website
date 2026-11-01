import { Link, router } from "@inertiajs/react";
import RoleName from "@/Components/Discord/RoleName";
import { NUMBER_FORMAT } from "@/Components/Permissions/PermissionsPage";
import cn from "@/Helpers/cn";

/** "N permission(s)" for a count. */
function describeCount(count) {
    return `${NUMBER_FORMAT.format(count)} ${count === 1 ? "permission" : "permissions"}`;
}

/**
 * Picks the role shown on the Access tab. From `md` up it is a scrolling list of links to
 * `management.permissions.index?role={id}`, each with the role's name in its Discord colour and its
 * permission count; the link for `selectedRoleId` carries `aria-current="page"`. Below `md` it is a
 * native select labelled "Role" that visits the chosen role's URL on change.
 */
export default function RolePicker({ roles, selectedRoleId }) {
    return (
        <>
            <nav aria-label="Roles" className="hidden md:block">
                <ul className="max-h-[70vh] space-y-1 overflow-y-auto">
                    {roles.map((role) => {
                        const isSelected = role.id === selectedRoleId;

                        return (
                            <li key={role.id}>
                                <Link
                                    href={route("management.permissions.index", { role: role.id })}
                                    preserveScroll
                                    preserveState
                                    aria-current={isSelected ? "page" : undefined}
                                    className={cn(
                                        "flex min-h-9 items-center gap-2 rounded-sm px-3 py-2 text-sm hover:bg-amber-600/10 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-amber-400",
                                        isSelected && "bg-amber-600/20",
                                    )}
                                >
                                    <RoleName name={role.name} color={role.primary_color} />
                                    <span aria-hidden="true" className="text-gray-500">
                                        ·
                                    </span>
                                    <span className="ml-auto shrink-0 text-gray-400 tabular-nums">
                                        {NUMBER_FORMAT.format(role.permissions_count)}
                                        <span className="sr-only">
                                            {role.permissions_count === 1 ? " permission" : " permissions"}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <div className="mb-6 md:hidden">
                <label htmlFor="role-picker" className="mb-1 block text-sm text-stone-300">
                    Role
                </label>
                <select
                    id="role-picker"
                    value={selectedRoleId ?? ""}
                    onChange={(event) =>
                        router.get(
                            route("management.permissions.index", { role: event.target.value }),
                            {},
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                    className="min-h-11 w-full rounded-md border border-amber-600 bg-stone-900 px-3 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                >
                    {roles.map((role) => (
                        <option key={role.id} value={role.id}>
                            {role.name} ({describeCount(role.permissions_count)})
                        </option>
                    ))}
                </select>
            </div>
        </>
    );
}
