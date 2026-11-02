import { useEffect, useId } from "react";
import { router, useForm } from "@inertiajs/react";
import { DialogTitle } from "@headlessui/react";
import Modal from "@/Components/Modal";
import {
    controlClassName,
    FormRow,
    OptionSelect,
    PrimaryButton,
    SecondaryButton,
    TextInput,
} from "@/Components/FormControls";
import { NUMBER_FORMAT } from "@/Components/Permissions/PermissionsPage";
import cn from "@/Helpers/cn";

const DESCRIPTION_MAX_LENGTH = 160;

const DESCRIPTION_COUNTER_FROM = 120;

/** The form values for `permission`, with a missing description or group as `""`. */
function formValuesFor(permission) {
    return {
        label: permission?.label ?? "",
        description: permission?.description ?? "",
        permission_group_id: permission?.permission_group_id ?? "",
    };
}

/**
 * The "Edit permission" dialog: shows `permission`'s read-only key and edits its name (label),
 * description and group. `groups` lists `{ id, name }` for the group select, which also offers
 * "No group". The form resets to `permission`'s values whenever the dialog opens or `permission` changes.
 *
 * Saving sends a PATCH to `management.permissions.update`, with an empty group sent as null, and
 * calls `onClose()` on success. Validation errors show beside their fields; a failed request raises
 * Master's error flash.
 */
export default function PermissionFormModal({ permission, isOpen, groups, onClose }) {
    const formId = useId();
    const labelId = `${formId}-label`;
    const descriptionId = `${formId}-description`;
    const descriptionCounterId = `${formId}-description-counter`;
    const descriptionErrorId = `${formId}-description-error`;
    const groupId = `${formId}-group`;

    const { data, setData, patch, transform, processing, errors, clearErrors } = useForm(formValuesFor(permission));

    useEffect(() => {
        if (isOpen) {
            setData(formValuesFor(permission));
            clearErrors();
        }
    }, [permission, isOpen]);

    const isCounterShown = data.description.length >= DESCRIPTION_COUNTER_FROM;

    const submit = (event) => {
        event.preventDefault();

        transform((values) => ({
            ...values,
            permission_group_id: values.permission_group_id === "" ? null : values.permission_group_id,
        }));

        patch(route("management.permissions.update", { permission: permission.id }), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onClose,
            onHttpException: () => {
                router.flash("error", `Couldn't save ${permission.label}. Try again.`);

                return false;
            },
            onNetworkError: () => router.flash("error", `Couldn't save ${permission.label}. Try again.`),
        });
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="lg">
            <form onSubmit={submit} className="p-6 text-white">
                <DialogTitle as="h2" className="mb-2 text-lg font-semibold">
                    Edit permission
                </DialogTitle>
                <p className="mb-6 text-sm text-gray-400">
                    Key{" "}
                    <code className="rounded-sm bg-brown-800 px-1 py-0.5 font-mono text-xs text-stone-200">
                        {permission?.name}
                    </code>{" "}
                    is used by the site's code and can't be changed.
                </p>

                <div className="flex flex-col gap-6">
                    <FormRow htmlFor={labelId} label="Name" required error={errors.label}>
                        <TextInput
                            id={labelId}
                            name="label"
                            value={data.label}
                            onChange={(event) => setData("label", event.target.value)}
                            required
                            maxLength={100}
                            invalid={Boolean(errors.label)}
                        />
                    </FormRow>

                    <FormRow htmlFor={descriptionId} label="Description">
                        <textarea
                            id={descriptionId}
                            name="description"
                            value={data.description}
                            onChange={(event) => setData("description", event.target.value)}
                            maxLength={DESCRIPTION_MAX_LENGTH}
                            rows={3}
                            aria-invalid={errors.description ? true : undefined}
                            aria-describedby={
                                [errors.description ? descriptionErrorId : null, isCounterShown ? descriptionCounterId : null]
                                    .filter(Boolean)
                                    .join(" ") || undefined
                            }
                            className={cn(
                                controlClassName,
                                "focus:border-amber-400 focus:outline-2 focus:outline-offset-2 focus:outline-amber-400 aria-invalid:border-red-400",
                            )}
                        />
                        <p id={descriptionErrorId} className={cn("text-sm text-red-300", !errors.description && "hidden")}>
                            {errors.description}
                        </p>
                        <p
                            id={descriptionCounterId}
                            className={cn("text-sm text-gray-400 tabular-nums", !isCounterShown && "hidden")}
                        >
                            {NUMBER_FORMAT.format(data.description.length)} of{" "}
                            {NUMBER_FORMAT.format(DESCRIPTION_MAX_LENGTH)} characters
                        </p>
                    </FormRow>

                    <FormRow htmlFor={groupId} label="Group" error={errors.permission_group_id}>
                        <OptionSelect
                            id={groupId}
                            name="permission_group_id"
                            value={data.permission_group_id}
                            onChange={(value) => setData("permission_group_id", value)}
                            options={groups.map((group) => ({ value: group.id, label: group.name }))}
                            placeholder="No group"
                            invalid={Boolean(errors.permission_group_id)}
                        />
                    </FormRow>
                </div>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <SecondaryButton onClick={onClose} className="w-full justify-center sm:w-auto">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton type="submit" processing={processing} className="w-full justify-center sm:w-auto">
                        Save permission
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
