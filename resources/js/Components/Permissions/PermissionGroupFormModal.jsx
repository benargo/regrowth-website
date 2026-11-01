import { useEffect, useId } from "react";
import { router, useForm } from "@inertiajs/react";
import { DialogTitle } from "@headlessui/react";
import Modal from "@/Components/Modal";
import { DangerButton, FormRow, PrimaryButton, SecondaryButton, TextInput } from "@/Components/FormControls";

/**
 * The group dialog. With no `group` it adds a group ("Add group"); with a `group` (`{ id, name,
 * permissions_count }`) it renames it ("Rename {name}"). Its one field, Name, resets to `group`'s
 * name, or empty, whenever the dialog opens or `group` changes.
 *
 * Adding sends a POST to `management.permission-groups.store`; renaming sends a PATCH to
 * `management.permission-groups.update`. Both call `onClose()` on success. Validation errors show
 * beside the field; a failed request raises Master's error flash.
 *
 * In rename mode an empty group offers "Delete group", which calls `onDelete(group)`.
 */
export default function PermissionGroupFormModal({ group, isOpen, onClose, onDelete }) {
    const nameId = `${useId()}-name`;
    const isRenaming = Boolean(group);

    const { data, setData, post, patch, processing, errors, clearErrors } = useForm({ name: group?.name ?? "" });

    useEffect(() => {
        if (isOpen) {
            setData({ name: group?.name ?? "" });
            clearErrors();
        }
    }, [group, isOpen]);

    const submit = (event) => {
        event.preventDefault();

        const failureMessage = isRenaming
            ? `Couldn't save ${group.name}. Try again.`
            : "Couldn't add the group. Try again.";

        const options = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onClose,
            onHttpException: () => {
                router.flash("error", failureMessage);

                return false;
            },
            onNetworkError: () => router.flash("error", failureMessage),
        };

        if (isRenaming) {
            patch(route("management.permission-groups.update", { permissionGroup: group.id }), options);
        } else {
            post(route("management.permission-groups.store"), options);
        }
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="p-6 text-white">
                {isRenaming ? (
                    <DialogTitle as="h2" className="mb-6 text-lg font-semibold">
                        Rename {group.name}
                    </DialogTitle>
                ) : (
                    <DialogTitle as="h2" className="mb-6 text-lg font-semibold">
                        Add group
                    </DialogTitle>
                )}

                <FormRow htmlFor={nameId} label="Name" required error={errors.name}>
                    <TextInput
                        id={nameId}
                        name="name"
                        value={data.name}
                        onChange={(event) => setData("name", event.target.value)}
                        required
                        maxLength={50}
                        invalid={Boolean(errors.name)}
                    />
                </FormRow>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                    {isRenaming && group.permissions_count === 0 && (
                        <DangerButton
                            onClick={() => onDelete(group)}
                            className="w-full justify-center sm:mr-auto sm:w-auto"
                        >
                            Delete group
                        </DangerButton>
                    )}
                    <SecondaryButton onClick={onClose} className="w-full justify-center sm:w-auto">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton type="submit" processing={processing} className="w-full justify-center sm:w-auto">
                        {isRenaming ? "Save group" : "Add group"}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
