/**
 * ManagePermissionGroups Page
 *
 * The Groups tab of the permissions pages: lists the permission groups in order, reorders them, and
 * adds, renames and deletes them.
 *
 * Props (via Inertia):
 * @prop {Object} permissionGroups  - PermissionGroupResource collection of every group, in order.
 *                                      `permissionGroups.data` lists groups, each with:
 *                                      - id: string
 *                                      - name: string
 *                                      - sort_order: number
 *                                      - permissions_count: number
 *
 * Behaviour:
 * - The groups render in `AutosavedSortableList`: dragging a row's grip, moving it from the grip with the
 *   keyboard, or using its up and down buttons saves the new order straight away by calling
 *   `management.permission-groups.reorder` via a PATCH request carrying every group id in the new order.
 * - "Add group" and each row's "Rename" button open `PermissionGroupFormModal`.
 * - The dialog's "Delete group" closes it and asks for confirmation. Confirming calls
 *   `management.permission-groups.destroy` via a DELETE request; a successful delete is announced and focus
 *   moves to the next row's Rename button, the previous row's when the last row went, or "Add group" when no
 *   groups are left. A failed request raises Master's error flash.
 * - When there are no groups, an empty state offers "Add group". The empty state and the list both stay
 *   mounted; whichever doesn't apply is hidden.
 */

import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import AutosavedSortableList from "@/Components/Datasets/AutosavedSortableList";
import ConfirmationModal from "@/Components/ConfirmationModal";
import EmptyState from "@/Components/EmptyState";
import { PrimaryButton } from "@/Components/FormControls";
import PermissionsPage, { LINK_CLASS, NUMBER_FORMAT } from "@/Components/Permissions/PermissionsPage";
import PermissionGroupFormModal from "@/Components/Permissions/PermissionGroupFormModal";
import cn from "@/Helpers/cn";
import { AutosaveProvider } from "@/Hooks/useAutosave";

const ADD_GROUP_KEY = "add-group";

/** "No permissions", "1 permission" or "{n} permissions". */
function permissionsText(count) {
    if (count === 0) {
        return "No permissions";
    }

    return count === 1 ? "1 permission" : `${NUMBER_FORMAT.format(count)} permissions`;
}

/** The key of `group`'s Rename button in the button ref map. */
function renameKey(group) {
    return `${group.id}-rename`;
}

export default function ManagePermissionGroups({ permissionGroups }) {
    const groups = permissionGroups.data;
    const hasGroups = groups.length > 0;

    const [formGroup, setFormGroup] = useState(null);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [deletingGroup, setDeletingGroup] = useState(null);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const buttonRefs = useRef(new Map());
    const pendingFocus = useRef(null);

    useEffect(() => {
        if (pendingFocus.current === null || isConfirmOpen) {
            return;
        }

        const button = buttonRefs.current.get(pendingFocus.current);

        if (button) {
            button.focus();
            pendingFocus.current = null;
        }
    }, [groups, isConfirmOpen]);

    /** A callback ref storing the element under `key` in the button ref map. */
    const registerButton = (key) => (element) => {
        if (element) {
            buttonRefs.current.set(key, element);
        } else {
            buttonRefs.current.delete(key);
        }
    };

    const openAdd = () => {
        setFormGroup(null);
        setIsFormOpen(true);
    };

    const openRename = (group) => {
        setFormGroup(group);
        setIsFormOpen(true);
    };

    const askToDelete = (group) => {
        setIsFormOpen(false);
        setDeletingGroup(group);
        setIsConfirmOpen(true);
    };

    const confirmDelete = () => {
        const group = deletingGroup;
        const index = groups.findIndex((candidate) => candidate.id === group.id);
        const neighbour = groups[index + 1] ?? groups[index - 1];
        const failureMessage = `Couldn't delete ${group.name}. Try again.`;

        setIsDeleting(true);

        router.delete(route("management.permission-groups.destroy", { permissionGroup: group.id }), {
            preserveScroll: true,
            onSuccess: (page) => {
                const isDeleted = !page.props.permissionGroups.data.some((candidate) => candidate.id === group.id);

                if (isDeleted) {
                    pendingFocus.current = neighbour ? renameKey(neighbour) : ADD_GROUP_KEY;
                }
            },
            onHttpException: () => {
                router.flash("error", failureMessage);

                return false;
            },
            onNetworkError: () => router.flash("error", failureMessage),
            onFinish: () => {
                setIsDeleting(false);
                setIsConfirmOpen(false);
            },
        });
    };

    return (
        <PermissionsPage currentTab="groups">
            <AutosaveProvider>
                <h2 className="sr-only">Groups</h2>

                <div className={cn(hasGroups && "hidden")}>
                    <EmptyState icon="layer-group" message="No groups yet.">
                        <PrimaryButton ref={hasGroups ? undefined : registerButton(ADD_GROUP_KEY)} onClick={openAdd}>
                            Add group
                        </PrimaryButton>
                    </EmptyState>
                </div>

                <div className={cn(!hasGroups && "hidden")}>
                    <div className="mb-4 flex justify-end">
                        <PrimaryButton
                            ref={hasGroups ? registerButton(ADD_GROUP_KEY) : undefined}
                            onClick={openAdd}
                            className="min-h-11 w-full justify-center sm:w-auto md:min-h-0"
                        >
                            Add group
                        </PrimaryButton>
                    </div>

                    <AutosavedSortableList
                        items={groups}
                        url={route("management.permission-groups.reorder")}
                        field="permission_group_ids"
                        saveKey="permission-group-order"
                        label="Permission groups"
                        className="divide-y divide-amber-600/25 border-y border-amber-600/25"
                        renderItem={(group, { isDragging, dragHandle }) => (
                            <div className={cn("flex items-center gap-3 py-2.5", isDragging && "bg-stone-900 px-2")}>
                                {dragHandle}
                                <div className="min-w-0 flex-1">
                                    <p className="break-words text-stone-200">{group.name}</p>
                                    <p className="text-sm text-gray-400">{permissionsText(group.permissions_count)}</p>
                                </div>
                                <button
                                    type="button"
                                    ref={isDragging ? undefined : registerButton(renameKey(group))}
                                    aria-label={`Rename ${group.name}`}
                                    onClick={() => openRename(group)}
                                    className={cn(
                                        LINK_CLASS,
                                        "inline-flex min-h-11 min-w-11 shrink-0 cursor-pointer items-center justify-center underline md:min-h-0 md:min-w-0",
                                    )}
                                >
                                    Rename
                                </button>
                            </div>
                        )}
                    />

                    <p className="mt-3 text-sm text-gray-400">Only empty groups can be deleted.</p>
                </div>

                <PermissionGroupFormModal
                    group={formGroup}
                    isOpen={isFormOpen}
                    onClose={() => setIsFormOpen(false)}
                    onDelete={askToDelete}
                />

                <ConfirmationModal
                    show={isConfirmOpen}
                    onClose={() => setIsConfirmOpen(false)}
                    onConfirm={confirmDelete}
                    title={`Delete ${deletingGroup?.name}?`}
                    confirmLabel="Delete group"
                    processing={isDeleting}
                    processingLabel="Deleting…"
                    variant="delete"
                    unmount={false}
                >
                    This can't be undone.
                </ConfirmationModal>
            </AutosaveProvider>
        </PermissionsPage>
    );
}
