/**
 * ManagePermissions Page
 *
 * Allows admins to manage which Discord roles have access to specific site permissions.
 *
 * Props (via Inertia):
 * @prop {Array}  discordRoles  - List of permission-granting Discord role objects. Each role has:
 *                                  - id: string
 *                                  - name: string
 *                                  - permissions: Array of permission objects { id, name }
 * @prop {Array}  groups        - List of all permission groups (strings) for filtering
 * @prop {Array}  permissions   - List of all available permissions for the current group { id, name, group }
 *
 * Behaviour:
 * - Each cell in the table is a toggle button that calls `management.permissions.permission.update`
 *   via a PATCH request (see PermissionController@update).
 * - Processing state is tracked per toggle (keyed as `${roleId}-${permissionId}`) to show
 *   individual loading states without locking the whole table.
 * - Shows an empty state linking to `management.discord-roles.index` when no role grants permissions.
 */

import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Dropdown from "@/Components/Dropdown";
import PageContainer from "@/Components/PageContainer";
import ToggleButton from "@/Components/ToggleButton";
import EmptyState from "@/Components/EmptyState";

function formatPermissionName(name) {
    return name
        .split("-")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

export default function ManagePermissions({ discordRoles, groups, permissions }) {
    const [processing, setProcessing] = useState({});

    const hasPermission = (role, permission) => {
        return role.permissions.some((p) => p.id === permission.id);
    };

    const togglePermission = (roleId, permission, currentState) => {
        const key = `${roleId}-${permission.id}`;
        setProcessing((prev) => ({ ...prev, [key]: true }));

        router.patch(
            route("management.permissions.permission.update", {
                group: permission.group,
                permission: permission.id,
            }),
            {
                discord_role_id: roleId,
                enabled: !currentState,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing((prev) => {
                        const next = { ...prev };
                        delete next[key];
                        return next;
                    });
                },
            },
        );
    };

    return (
        <Master title="Manage Permissions">
            <SharedHeader title="Manage Permissions" backgroundClass="bg-arcatraz" />
            <PageContainer>
                <p className="mb-6 text-gray-400">
                    Control which Discord roles have access to specific site features. Changes take effect immediately.
                </p>
                {/* Group navigation */}
                <nav className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="flex items-center justify-between rounded border border-amber-600 px-4 py-2 text-amber-500 transition-colors hover:bg-amber-600/20">
                                {groups.find((g) => g.active)?.name ?? "Select Group"}
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="left" width="48">
                            <div className="bg-brown-800 rounded-md border border-amber-600 shadow-lg">
                                {groups.map((group) => (
                                    <Dropdown.Link
                                        key={group.slug}
                                        href={route("management.permissions.group.show", { group: group.slug })}
                                        className={group.active ? "bg-brown-800" : ""}
                                    >
                                        {group.name}
                                    </Dropdown.Link>
                                ))}
                            </div>
                        </Dropdown.Content>
                    </Dropdown>
                    <Link
                        href={route("management.discord-roles.index")}
                        className="text-amber-500 underline-offset-4 hover:text-amber-400 hover:underline"
                    >
                        Choose which Discord roles grant permissions
                    </Link>
                </nav>

                {/* Empty state */}
                <div className={discordRoles.length === 0 ? "" : "hidden"}>
                    <EmptyState icon="shield-check" iconStyle="light" message="No Discord roles grant permissions yet.">
                        <Link
                            href={route("management.discord-roles.index")}
                            className="text-amber-500 underline underline-offset-4 hover:text-amber-400"
                        >
                            Manage Discord roles
                        </Link>
                    </EmptyState>
                </div>

                {/* Permission table */}
                <div className={discordRoles.length === 0 ? "hidden" : "overflow-x-auto"}>
                    <table className="w-full border-collapse">
                        <thead>
                            <tr className="border-b-2 border-amber-600">
                                <th className="px-4 py-3 text-left">Discord Role</th>
                                {permissions.map((permission) => (
                                    <th key={permission.id} className="px-4 py-3 text-center">
                                        <div className="text-sm font-semibold">
                                            {formatPermissionName(permission.name)}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {discordRoles.map((role) => (
                                <tr key={role.id} className="border-b border-amber-600/30 hover:bg-amber-600/10">
                                    <td className="px-4 py-3 font-medium">{role.name}</td>
                                    {permissions.map((permission) => {
                                        const enabled = hasPermission(role, permission);
                                        const key = `${role.id}-${permission.id}`;

                                        return (
                                            <td key={permission.id} className="px-4 py-3 text-center">
                                                <ToggleButton
                                                    enabled={enabled}
                                                    processing={!!processing[key]}
                                                    onToggle={() => togglePermission(role.id, permission, enabled)}
                                                />
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </PageContainer>
        </Master>
    );
}
