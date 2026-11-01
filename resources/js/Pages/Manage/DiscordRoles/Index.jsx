/**
 * ManageDiscordRoles Page
 *
 * Lists every Discord role in Discord order and lets admins choose which roles
 * grant site permissions and which roles are visible on the site.
 *
 * Props (via Inertia):
 * @prop {Object} discordRoles      - DiscordRoleResource collection. `discordRoles.data` lists roles, each with:
 *                                      - id: string
 *                                      - name: string
 *                                      - position: number
 *                                      - primary_color: string|null (hex without `#`)
 *                                      - is_visible: bool
 *                                      - grants_permissions: bool
 *                                      - users_count: number
 *                                      - permissions_count: number
 *
 * Behaviour:
 * - Each toggle calls `management.discord-roles.update` via a PATCH request carrying only the changed flag.
 * - Processing state is tracked per toggle (keyed as `${roleId}-${flag}`) to show
 *   individual loading states without locking the whole table.
 * - Turning off Grants permissions for a role with permissions opens a confirmation modal first.
 */

import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ConfirmationModal from "@/Components/ConfirmationModal";
import ToggleButton from "@/Components/ToggleButton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import modelColor from "@/Helpers/ModelColor";

/** A round swatch in the role's primary colour, hidden when the role has none. */
function RoleSwatch({ color }) {
    const { className, style } = modelColor(color, ["bg"]);

    return (
        <span
            aria-hidden="true"
            className={`inline-block size-3 shrink-0 rounded-full ${color ? className : "hidden"}`}
            style={style}
        />
    );
}

export default function ManageDiscordRoles({ discordRoles }) {
    const [processing, setProcessing] = useState({});
    const [pendingRole, setPendingRole] = useState(null);
    const [isConfirming, setIsConfirming] = useState(false);

    const updateFlag = (role, flag, currentState) => {
        const key = `${role.id}-${flag}`;
        setProcessing((prev) => ({ ...prev, [key]: true }));

        router.patch(
            route("management.discord-roles.update", { discordRole: role.id }),
            { [flag]: !currentState },
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

    const toggleGrantsPermissions = (role) => {
        if (role.grants_permissions && role.permissions_count > 0) {
            setPendingRole(role);
            setIsConfirming(true);

            return;
        }

        updateFlag(role, "grants_permissions", role.grants_permissions);
    };

    const confirmRevoke = () => {
        updateFlag(pendingRole, "grants_permissions", true);
        setIsConfirming(false);
    };

    return (
        <Master title="Discord Roles">
            <SharedHeader title="Discord Roles" backgroundClass="bg-arcatraz" />
            <PageContainer>
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <p className="text-gray-400">
                        Choose which Discord roles grant site permissions and which appear on the site. Names, colours
                        and order come from Discord.
                    </p>
                    <Link
                        href={route("management.permissions.index")}
                        className="text-amber-500 underline-offset-4 hover:text-amber-400 hover:underline"
                    >
                        Manage site permissions
                    </Link>
                </div>

                <Table>
                    <TableHead>
                        <tr>
                            <TableHeader className="text-left">Discord Role</TableHeader>
                            <TableHeader className="text-right">Members</TableHeader>
                            <TableHeader className="text-center">Grants permissions</TableHeader>
                            <TableHeader className="text-right">Permissions</TableHeader>
                            <TableHeader className="text-center">Visible</TableHeader>
                        </tr>
                    </TableHead>
                    <TableBody>
                        {discordRoles.data.map((role) => (
                            <TableRow key={role.id}>
                                <TableCell className="font-medium">
                                    <span className="flex items-center gap-3">
                                        <RoleSwatch color={role.primary_color} />
                                        {role.name}
                                    </span>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{role.users_count}</TableCell>
                                <TableCell className="text-center">
                                    <ToggleButton
                                        enabled={role.grants_permissions}
                                        processing={!!processing[`${role.id}-grants_permissions`]}
                                        onToggle={() => toggleGrantsPermissions(role)}
                                        enabledLabel="Yes"
                                        disabledLabel="No"
                                    />
                                </TableCell>
                                <TableCell className="text-right text-gray-400 tabular-nums">
                                    {role.grants_permissions ? role.permissions_count : ""}
                                </TableCell>
                                <TableCell className="text-center">
                                    <ToggleButton
                                        enabled={role.is_visible}
                                        processing={!!processing[`${role.id}-is_visible`]}
                                        onToggle={() => updateFlag(role, "is_visible", role.is_visible)}
                                        enabledLabel="Visible"
                                        disabledLabel="Hidden"
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </PageContainer>

            <ConfirmationModal
                show={isConfirming}
                onClose={() => setIsConfirming(false)}
                onConfirm={confirmRevoke}
                title={`Stop ${pendingRole?.name ?? "this role"} granting permissions?`}
                confirmLabel="Remove permissions"
                variant="delete"
            >
                This removes {pendingRole?.permissions_count}{" "}
                {pendingRole?.permissions_count === 1 ? "permission" : "permissions"} from {pendingRole?.name}.
            </ConfirmationModal>
        </Master>
    );
}
