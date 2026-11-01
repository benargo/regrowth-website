/**
 * ManagePermissions Page
 *
 * The Permissions tab of the permissions pages: lists every permission by group, with a search, and edits a
 * permission's name, description and group.
 *
 * Props (via Inertia):
 * @prop {Object} permissionGroups      - PermissionGroupResource collection of every group, in order, including
 *                                          empty ones. `permissionGroups.data` lists groups, each with:
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
 *                                          - discord_roles_count: number
 *
 * Behaviour:
 * - A search box filters the permissions by label or key, case-insensitively. While a query is set, a polite
 *   live region announces how many permissions match, groups with no matches are hidden, and a message shows
 *   when nothing matches.
 * - From `md` up the permissions show in a table with one body per group, then "Ungrouped" when any have no
 *   group. Below `md` they show in a stacked list under group headings.
 * - Each permission's "Edit" button opens `PermissionFormModal`. The last-edited permission stays in state
 *   after the dialog closes.
 */

import { useState } from "react";
import { FormRow, TextInput } from "@/Components/FormControls";
import PermissionsPage, { LINK_CLASS, NUMBER_FORMAT } from "@/Components/Permissions/PermissionsPage";
import PermissionFormModal from "@/Components/Permissions/PermissionFormModal";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import cn from "@/Helpers/cn";

/** Whether `permission`'s label or key contains `query` (already lower-cased). */
function matchesQuery(permission, query) {
    return permission.label.toLowerCase().includes(query) || permission.name.toLowerCase().includes(query);
}

/** "1 role" or "{n} roles". */
function rolesText(count) {
    return count === 1 ? "1 role" : `${NUMBER_FORMAT.format(count)} roles`;
}

/** The Edit button for `permission`. */
function EditButton({ permission, onEdit, className }) {
    return (
        <button
            type="button"
            aria-label={`Edit ${permission.label}`}
            onClick={() => onEdit(permission)}
            className={cn(LINK_CLASS, "cursor-pointer underline", className)}
        >
            Edit
        </button>
    );
}

/** The permissions table shown from `md` up: one body per section in `sections`. */
function PermissionsTable({ sections, onEdit }) {
    return (
        <div className="hidden md:block">
            <Table>
                <TableHead>
                    <tr>
                        <TableHeader scope="col" className="text-left">
                            Name
                        </TableHeader>
                        <TableHeader scope="col" className="text-left">
                            Group
                        </TableHeader>
                        <TableHeader scope="col" className="text-right">
                            Roles
                        </TableHeader>
                        <TableHeader scope="col" className="text-right">
                            <span className="sr-only">Actions</span>
                        </TableHeader>
                    </tr>
                </TableHead>
                {sections.map((section) => (
                    <TableBody key={section.key}>
                        <tr>
                            <th
                                scope="rowgroup"
                                colSpan={4}
                                className="bg-brown-800/60 px-4 pt-5 pb-2 text-left text-lg font-semibold text-white"
                            >
                                {section.title}
                            </th>
                        </tr>
                        {section.permissions.length === 0 && (
                            <tr>
                                <TableCell colSpan={4} className="text-sm text-gray-400">
                                    No permissions
                                </TableCell>
                            </tr>
                        )}
                        {section.permissions.map((permission) => (
                            <TableRow key={permission.id}>
                                <th scope="row" className="px-4 py-3 text-left font-normal">
                                    <span className="block text-stone-200">{permission.label}</span>
                                    <span className="block font-mono text-xs text-gray-500">{permission.name}</span>
                                </th>
                                <TableCell className="text-stone-300">{section.groupName ?? "No group"}</TableCell>
                                <TableCell className="text-right text-stone-300 tabular-nums">
                                    {NUMBER_FORMAT.format(permission.discord_roles_count)}
                                </TableCell>
                                <TableCell className="text-right">
                                    <EditButton permission={permission} onEdit={onEdit} />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                ))}
            </Table>
        </div>
    );
}

/** The stacked permissions list shown below `md`: one headed list per section in `sections`. */
function PermissionsList({ sections, onEdit }) {
    return (
        <div className="space-y-8 md:hidden">
            {sections.map((section) => (
                <section key={section.key}>
                    <h3 className="border-b border-amber-600 pb-1 text-lg font-semibold text-white">{section.title}</h3>
                    {section.permissions.length === 0 && <p className="py-2.5 text-sm text-gray-400">No permissions</p>}
                    <ul className="divide-y divide-amber-600/25">
                        {section.permissions.map((permission) => (
                            <li key={permission.id} className="flex items-center justify-between gap-4 py-2.5">
                                <div className="min-w-0">
                                    <p className="text-stone-200">{permission.label}</p>
                                    <p className="font-mono text-xs break-all text-gray-500">{permission.name}</p>
                                    <p className="mt-1 text-sm text-gray-400">
                                        {section.groupName ?? "No group"} · {rolesText(permission.discord_roles_count)}
                                    </p>
                                </div>
                                <EditButton
                                    permission={permission}
                                    onEdit={onEdit}
                                    className="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center"
                                />
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}

export default function ManagePermissions({ permissionGroups, ungroupedPermissions }) {
    const [searchQuery, setSearchQuery] = useState("");
    const [editingPermission, setEditingPermission] = useState(null);
    const [isEditOpen, setIsEditOpen] = useState(false);

    const normalisedQuery = searchQuery.trim().toLowerCase();
    const isSearching = normalisedQuery !== "";

    const allSections = permissionGroups.data.map((group) => ({
        key: group.id,
        title: group.name,
        groupName: group.name,
        permissions: group.permissions,
    }));

    if (ungroupedPermissions.data.length > 0) {
        allSections.push({
            key: "ungrouped",
            title: "Ungrouped",
            groupName: null,
            permissions: ungroupedPermissions.data,
        });
    }

    const sections = isSearching
        ? allSections
              .map((section) => ({
                  ...section,
                  permissions: section.permissions.filter((permission) => matchesQuery(permission, normalisedQuery)),
              }))
              .filter((section) => section.permissions.length > 0)
        : allSections;

    const matchCount = sections.reduce((total, section) => total + section.permissions.length, 0);
    const announcement = !isSearching
        ? ""
        : matchCount === 1
          ? "1 permission matches"
          : `${NUMBER_FORMAT.format(matchCount)} permissions match`;

    const openEdit = (permission) => {
        setEditingPermission(permission);
        setIsEditOpen(true);
    };

    return (
        <PermissionsPage currentTab="permissions">
            <h2 className="sr-only">Permissions</h2>

            <div className="mb-6 max-w-md">
                <FormRow htmlFor="permission-search" label="Search permissions">
                    <TextInput
                        id="permission-search"
                        type="search"
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
                        autoComplete="off"
                        spellCheck={false}
                    />
                </FormRow>
                <p role="status" aria-live="polite" className="mt-2 min-h-5 text-sm text-gray-400">
                    {announcement}
                </p>
            </div>

            {isSearching && matchCount === 0 ? (
                <p className="text-gray-400">No permissions match “{searchQuery.trim()}”.</p>
            ) : (
                <>
                    <PermissionsTable sections={sections} onEdit={openEdit} />
                    <PermissionsList sections={sections} onEdit={openEdit} />
                </>
            )}

            <PermissionFormModal
                permission={editingPermission}
                isOpen={isEditOpen}
                groups={permissionGroups.data}
                onClose={() => setIsEditOpen(false)}
            />
        </PermissionsPage>
    );
}
