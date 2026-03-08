/**
 * ManagePermissions Page
 *
 * Allows admins (and non-admins with restrictions) to manage which Discord roles
 * have access to specific site permissions.
 *
 * Props (via Inertia):
 * @prop {Array}  discordRoles  - List of Discord role objects. Each role has:
 *                                  - id: number
 *                                  - name: string
 *                                  - permissions: Array of permission objects { id, name }
 * @prop {Array}  groups        - List of all permission groups (strings) for filtering
 * @prop {Array}  permissions   - List of all available permissions for the current group { id, name, group }
 *
 * Auth (via usePage):
 * @prop {Object} auth.user              - The authenticated user
 * @prop {bool}   auth.user.admin        - Whether the user is a full admin
 * @prop {string} auth.user.highest_role - The user's highest Discord role name;
 *                                         non-admins cannot toggle permissions for their own role
 *
 * Behaviour:
 * - Each cell in the table is a toggle button that calls `dashboard.permissions.toggle`
 *   via a POST request (see PermissionController@toggle).
 * - Toggles are disabled for a non-admin user's own highest role to prevent privilege escalation.
 * - Processing state is tracked per toggle (keyed as `${roleId}-${permissionId}`) to show
 *   individual loading states without locking the whole table.
 */

import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Dropdown from "@/Components/Dropdown";

function PermissionToggle({ enabled, processing, onToggle, disabled }) {
    return (
        <button
            onClick={onToggle}
            disabled={disabled || processing}
            className={`rounded px-3 py-1 text-sm font-medium transition-colors ${
                enabled
                    ? "bg-green-600" + (disabled ? "" : " hover:bg-green-700")
                    : "bg-gray-600" + (disabled ? "" : " hover:bg-gray-700")
            } ${processing ? "cursor-wait opacity-50" : ""} ${disabled ? "cursor-not-allowed opacity-50" : ""}`}
        >
            {processing ? "..." : enabled ? "Enabled" : "Disabled"}
        </button>
    );
}

function formatPermissionName(name) {
    return name
        .split("-")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

export default function ManagePermissions({ discordRoles, groups, permissions }) {
    const { auth } = usePage().props;
    const [processing, setProcessing] = useState({});

    const isRoleDisabled = (role) => {
        return !auth.user.admin && role.name === auth.user.highest_role;
    };

    const hasPermission = (role, permission) => {
        return role.permissions.some((p) => p.id === permission.id);
    };

    const togglePermission = (roleId, permissionId, currentState) => {
        const key = `${roleId}-${permissionId}`;
        setProcessing((prev) => ({ ...prev, [key]: true }));

        router.post(
            route("dashboard.permissions.toggle"),
            {
                discord_role_id: roleId,
                permission_id: permissionId,
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
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <p className="mb-6 text-gray-400">
                        Control which Discord roles have access to specific site features. Changes take effect
                        immediately.
                    </p>
                    {/* Group navigation */}
                    <nav className="mb-6">
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button className="flex items-center justify-between rounded border border-amber-600 px-4 py-2 text-amber-600 transition-colors hover:bg-amber-600/20">
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
                                <div className="rounded-md border border-amber-600 bg-brown shadow-lg">
                                    {groups.map((group) => (
                                        <Dropdown.Link
                                            key={group.slug}
                                            href={route("dashboard.permissions.show-group", { group: group.slug })}
                                            className={group.active ? "bg-brown-800" : ""}
                                        >
                                            {group.name}
                                        </Dropdown.Link>
                                    ))}
                                </div>
                            </Dropdown.Content>
                        </Dropdown>
                    </nav>

                    {/* Permission table */}
                    <div className="overflow-x-auto">
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
                                {discordRoles.map((role) => {
                                    const disabled = isRoleDisabled(role);

                                    return (
                                        <tr
                                            key={role.id}
                                            className="border-b border-amber-600/30 hover:bg-amber-600/10"
                                        >
                                            <td className="px-4 py-3 font-medium">{role.name}</td>
                                            {permissions.map((permission) => {
                                                const enabled = hasPermission(role, permission);
                                                const key = `${role.id}-${permission.id}`;

                                                return (
                                                    <td key={permission.id} className="px-4 py-3 text-center">
                                                        <PermissionToggle
                                                            enabled={enabled}
                                                            processing={!!processing[key]}
                                                            onToggle={() =>
                                                                togglePermission(role.id, permission.id, enabled)
                                                            }
                                                            disabled={disabled}
                                                        />
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Master>
    );
}
