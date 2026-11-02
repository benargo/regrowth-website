/**
 * ManagePermissionsAccess Page
 *
 * The Access tab of the permissions pages: switches each permission on or off for one Discord role
 * at a time.
 *
 * Props (via Inertia):
 * @prop {Object} discordRoles          - DiscordRoleResource collection of the roles that grant permissions, in
 *                                          Discord order. `discordRoles.data` lists roles, each with:
 *                                          - id: string
 *                                          - name: string
 *                                          - primary_color: string|null (hex without `#`)
 *                                          - permissions_count: number
 * @prop {string|null} selectedRoleId   - The id of the role shown, or null when no role grants permissions.
 * @prop {Object} permissionGroups      - PermissionGroupResource collection of the groups that hold permissions,
 *                                          in order. `permissionGroups.data` lists groups, each with:
 *                                          - id: string
 *                                          - name: string
 *                                          - sort_order: number
 *                                          - permissions: Array of permissions (see below)
 * @prop {Object} ungroupedPermissions  - PermissionResource collection of the permissions with no group. Each
 *                                          permission has:
 *                                          - id: number
 *                                          - name: string
 *                                          - label: string
 *                                          - description: string|null
 *                                          - permission_group_id: string|null
 *                                          - discord_role_ids: string[]
 *
 * Behaviour:
 * - A role picker (a list from `md` up, a select below it) visits `?role={id}` to change the role shown.
 * - The chosen role's permissions show in one section per group, then "Ungrouped" when any have no group.
 * - Each switch, and each section's "Turn all on" / "Turn all off", calls `management.permissions.access.update`
 *   via a PATCH request carrying the role id, the permission ids and whether they are enabled.
 * - Busy state is tracked per permission id; "Turn all" marks every id in its section busy.
 * - A successful change is announced in a polite live region; a failed one raises Master's error flash.
 * - The busy state and the announcement reset when the role shown changes.
 * - When no role grants permissions, an empty state links to the Discord Roles page.
 */

import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import EmptyState from "@/Components/EmptyState";
import RoleName from "@/Components/Discord/RoleName";
import PermissionsPage, { LINK_CLASS, NUMBER_FORMAT } from "@/Components/Permissions/PermissionsPage";
import RolePicker from "@/Components/Permissions/RolePicker";
import AccessSection from "@/Components/Permissions/AccessSection";

/** Whether `role` holds `permission`. */
function isHeldBy(permission, role) {
    return permission.discord_role_ids.includes(role.id);
}

/**
 * The chosen role's heading, its permission sections and the live region. `sections` lists
 * `{ key, title, announceAs, permissions }`.
 */
function RoleAccess({ role, sections }) {
    const [busyIds, setBusyIds] = useState(() => new Set());
    const [announcement, setAnnouncement] = useState("");

    const allPermissions = sections.flatMap((section) => section.permissions);
    const heldCount = allPermissions.filter((permission) => isHeldBy(permission, role)).length;

    const save = (permissionIds, isEnabled, message) => {
        setBusyIds((previous) => new Set([...previous, ...permissionIds]));

        router.patch(
            route("management.permissions.access.update"),
            { discord_role_id: role.id, permission_ids: permissionIds, enabled: isEnabled },
            {
                async: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setAnnouncement(message),
                onError: (errors) => router.flash("error", Object.values(errors)[0]),
                onHttpException: () => {
                    router.flash("error", `Couldn't update ${role.name}. Try again.`);

                    return false;
                },
                onNetworkError: () => router.flash("error", `Couldn't update ${role.name}. Try again.`),
                onFinish: () => {
                    setBusyIds((previous) => {
                        const next = new Set(previous);
                        permissionIds.forEach((id) => next.delete(id));
                        return next;
                    });
                },
            },
        );
    };

    const togglePermission = (permission) => {
        const isEnabled = !isHeldBy(permission, role);

        save([permission.id], isEnabled, `${role.name}: ${permission.label} turned ${isEnabled ? "on" : "off"}.`);
    };

    const toggleSection = (section) => {
        const isEnabled = !section.permissions.every((permission) => isHeldBy(permission, role));

        save(
            section.permissions.map((permission) => permission.id),
            isEnabled,
            `${role.name}: every ${section.announceAs} permission turned ${isEnabled ? "on" : "off"}.`,
        );
    };

    return (
        <div>
            <div className="mb-6">
                <h2 className="text-xl">
                    <RoleName name={role.name} color={role.primary_color} />
                </h2>
                <p className="mt-1 text-stone-300 tabular-nums">
                    {NUMBER_FORMAT.format(heldCount)} of {NUMBER_FORMAT.format(allPermissions.length)} permissions
                </p>
                <p className="mt-1 text-sm text-gray-400">Changes save as soon as you flip a switch.</p>
            </div>

            <div className="space-y-8">
                {sections.map((section) => (
                    <AccessSection
                        key={section.key}
                        title={section.title}
                        permissions={section.permissions}
                        role={role}
                        busyIds={busyIds}
                        onToggle={togglePermission}
                        onToggleAll={() => toggleSection(section)}
                    />
                ))}
            </div>

            <p role="status" aria-live="polite" className="mt-3 min-h-5 text-sm text-gray-400">
                {announcement}
            </p>
        </div>
    );
}

export default function ManagePermissionsAccess({
    discordRoles,
    selectedRoleId,
    permissionGroups,
    ungroupedPermissions,
}) {
    const selectedRole = discordRoles.data.find((role) => role.id === selectedRoleId);

    if (!selectedRole) {
        return (
            <PermissionsPage currentTab="access">
                <EmptyState icon="shield-check" iconStyle="light" message="No Discord roles grant permissions yet.">
                    <Link href={route("management.discord-roles.index")} className={LINK_CLASS}>
                        Choose which Discord roles grant permissions
                    </Link>
                </EmptyState>
            </PermissionsPage>
        );
    }

    const sections = permissionGroups.data.map((group) => ({
        key: group.id,
        title: group.name,
        announceAs: group.name,
        permissions: group.permissions,
    }));

    if (ungroupedPermissions.data.length > 0) {
        sections.push({
            key: "ungrouped",
            title: "Ungrouped",
            announceAs: "ungrouped",
            permissions: ungroupedPermissions.data,
        });
    }

    return (
        <PermissionsPage currentTab="access">
            <div className="md:grid md:grid-cols-[14rem_1fr] md:gap-8">
                <RolePicker roles={discordRoles.data} selectedRoleId={selectedRoleId} />
                <RoleAccess key={selectedRole.id} role={selectedRole} sections={sections} />
            </div>
        </PermissionsPage>
    );
}
