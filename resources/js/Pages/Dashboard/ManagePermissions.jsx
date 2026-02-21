import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";

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

export default function ManagePermissions({ discordRoles, permissions }) {
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
