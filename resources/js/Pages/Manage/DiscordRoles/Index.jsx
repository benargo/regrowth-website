/**
 * ManageDiscordRoles Page
 *
 * Lists every Discord role in Discord order, each name in its Discord colour, and lets admins
 * choose which roles grant site permissions and which roles are shown on the site.
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
 * - Each switch calls `management.discord-roles.update` via a PATCH request carrying only the changed flag.
 * - Busy state is tracked per switch (keyed as `${roleId}-${flag}`), so one switch can be in flight
 *   without locking the rest.
 * - Turning off Grants permissions for a role with permissions opens a confirmation modal first.
 * - A successful change is announced in a polite live region; a failed one raises Master's error flash.
 * - Rows for roles that neither grant permissions nor are shown on the site are dimmed.
 * - Below `md` the roles render as a stacked list instead of a table.
 * - A tab nav filters the list to "Roles in use" (the default) or "All roles" (`?roles=all`). A role
 *   switched out of use stays listed until the filter changes or the page loads again.
 */

import { useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ConfirmationModal from "@/Components/ConfirmationModal";
import EmptyState from "@/Components/EmptyState";
import TabNav from "@/Components/TabNav";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Icon from "@/Components/FontAwesome/Icon";
import Switch from "@/Components/Switch";
import RoleName from "@/Components/Discord/RoleName";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import { LINK_CLASS, NUMBER_FORMAT } from "@/Components/Permissions/PermissionsPage";
import cn from "@/Helpers/cn";

/** Whether a role grants permissions or is shown on the site. */
function isRoleInUse(role) {
    return role.grants_permissions || role.is_visible;
}

/** The sentence announced after `flag` on `role` changes to `isEnabled`. */
function describeChange(role, flag, isEnabled) {
    if (flag === "grants_permissions") {
        return isEnabled ? `${role.name} now grants permissions.` : `${role.name} no longer grants permissions.`;
    }

    return isEnabled ? `${role.name} is now shown on the site.` : `${role.name} is now hidden from the site.`;
}

/** "N permission(s)" linking to the site permissions page, for a role that grants any. */
function PermissionsLink({ role }) {
    if (!role.grants_permissions || role.permissions_count === 0) {
        return null;
    }

    return (
        <Link href={route("management.permissions.index", { role: role.id })} className={LINK_CLASS}>
            {NUMBER_FORMAT.format(role.permissions_count)} {role.permissions_count === 1 ? "permission" : "permissions"}
        </Link>
    );
}

/**
 * The roles as a table from `md` up and a stacked list below it. With the "used" filter it lists the
 * roles that were in use when it mounted, so a role switched out of use stays until it remounts.
 * `grantsSwitch` and `visibilitySwitch` render a role's two switches.
 */
function RoleList({ roles, filter, grantsSwitch, visibilitySwitch }) {
    const [keptRoleIds] = useState(() => new Set(roles.filter(isRoleInUse).map((role) => role.id)));
    const listedRoles = filter === "all" ? roles : roles.filter((role) => keptRoleIds.has(role.id));

    if (listedRoles.length === 0) {
        return (
            <EmptyState icon="users" message="No roles are in use yet.">
                <Link href={route("management.discord-roles.index", { roles: "all" })} className={LINK_CLASS}>
                    Show all roles
                </Link>
            </EmptyState>
        );
    }

    return (
        <>
            <div className="hidden md:block">
                <Table className="table-fixed">
                    <TableHead>
                        <tr>
                            <TableHeader scope="col" className="text-left">
                                Role
                            </TableHeader>
                            <TableHeader scope="col" className="w-32 text-right">
                                Members
                            </TableHeader>
                            <TableHeader scope="col" className="w-60 text-left">
                                Grants permissions
                            </TableHeader>
                            <TableHeader scope="col" className="w-40 text-left">
                                Shown on site
                            </TableHeader>
                        </tr>
                    </TableHead>
                    <TableBody>
                        {listedRoles.map((role) => {
                            const isDimmed = !isRoleInUse(role);

                            return (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <RoleName name={role.name} color={role.primary_color} isDimmed={isDimmed} />
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            "text-right tabular-nums",
                                            isDimmed ? "text-gray-500" : "text-gray-300",
                                        )}
                                    >
                                        {NUMBER_FORMAT.format(role.users_count)}
                                    </TableCell>
                                    <TableCell>
                                        <span className="flex items-center gap-3">
                                            {grantsSwitch(role)}
                                            <PermissionsLink role={role} />
                                        </span>
                                    </TableCell>
                                    <TableCell>{visibilitySwitch(role)}</TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            <ul className="divide-y divide-amber-600 border-y border-amber-600 md:hidden">
                {listedRoles.map((role) => {
                    const isDimmed = !isRoleInUse(role);

                    return (
                        <li key={role.id} className="py-3">
                            <div className="flex items-baseline justify-between gap-3">
                                <RoleName name={role.name} color={role.primary_color} isDimmed={isDimmed} />
                                <span
                                    className={cn(
                                        "shrink-0 text-sm tabular-nums",
                                        isDimmed ? "text-gray-500" : "text-gray-400",
                                    )}
                                >
                                    {NUMBER_FORMAT.format(role.users_count)}{" "}
                                    {role.users_count === 1 ? "member" : "members"}
                                </span>
                            </div>
                            <div className="mt-2.5 flex flex-col gap-2 text-sm text-stone-300">
                                <label className="flex items-center gap-3">
                                    <span>Grants permissions</span>
                                    <PermissionsLink role={role} />
                                    <span className="ml-auto">{grantsSwitch(role)}</span>
                                </label>
                                <label className="flex items-center gap-3">
                                    <span>Shown on site</span>
                                    <span className="ml-auto">{visibilitySwitch(role)}</span>
                                </label>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </>
    );
}

export default function ManageDiscordRoles({ discordRoles }) {
    const [processing, setProcessing] = useState({});
    const [pendingRole, setPendingRole] = useState(null);
    const [isConfirming, setIsConfirming] = useState(false);
    const [announcement, setAnnouncement] = useState("");
    const filter = new URL(usePage().url, window.location.origin).searchParams.get("roles") === "all" ? "all" : "used";

    const isBusy = (role, flag) => !!processing[`${role.id}-${flag}`];

    const updateFlag = (role, flag, currentState) => {
        const key = `${role.id}-${flag}`;
        setProcessing((prev) => ({ ...prev, [key]: true }));

        router.patch(
            route("management.discord-roles.update", { discordRole: role.id }),
            { [flag]: !currentState },
            {
                async: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setAnnouncement(describeChange(role, flag, !currentState)),
                onError: (errors) => router.flash("error", Object.values(errors)[0]),
                onHttpException: () => {
                    router.flash("error", `Couldn't update ${role.name}. Try again.`);

                    return false;
                },
                onNetworkError: () => router.flash("error", `Couldn't update ${role.name}. Try again.`),
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

    const toggleVisibility = (role) => updateFlag(role, "is_visible", role.is_visible);

    const confirmRevoke = () => {
        updateFlag(pendingRole, "grants_permissions", true);
        setIsConfirming(false);
    };

    const grantsSwitch = (role) => (
        <Switch
            checked={role.grants_permissions}
            isBusy={isBusy(role, "grants_permissions")}
            onChange={() => toggleGrantsPermissions(role)}
            label={`${role.name} grants permissions`}
        />
    );

    const visibilitySwitch = (role) => (
        <Switch
            checked={role.is_visible}
            isBusy={isBusy(role, "is_visible")}
            onChange={() => toggleVisibility(role)}
            label={`${role.name} is shown on site`}
        />
    );

    return (
        <Master title="Discord Roles">
            <SharedHeader title="Discord Roles" backgroundClass="bg-arcatraz" />
            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink
                        href={route("management.dashboard")}
                        className="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to officers' dashboard
                    </ToolNavLink>
                </div>
            </ToolNav>
            <PageContainer>
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <p className="text-gray-400">
                        Choose which Discord roles grant site permissions and which are shown on the site. Names,
                        colours and order come from Discord.
                    </p>
                    <Link href={route("management.permissions.index")} className={LINK_CLASS}>
                        Manage site permissions
                    </Link>
                </div>

                <TabNav
                    ariaLabel="Filter roles"
                    currentTab={filter}
                    tabs={[
                        {
                            name: "used",
                            label: `Roles in use (${discordRoles.data.filter(isRoleInUse).length})`,
                            href: route("management.discord-roles.index"),
                        },
                        {
                            name: "all",
                            label: `All roles (${discordRoles.data.length})`,
                            href: route("management.discord-roles.index", { roles: "all" }),
                        },
                    ]}
                />

                <RoleList
                    key={filter}
                    roles={discordRoles.data}
                    filter={filter}
                    grantsSwitch={grantsSwitch}
                    visibilitySwitch={visibilitySwitch}
                />

                <p role="status" aria-live="polite" className="mt-3 min-h-5 text-sm text-gray-400">
                    {announcement}
                </p>
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
