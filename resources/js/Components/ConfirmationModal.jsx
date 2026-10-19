import { Description, DialogTitle } from '@headlessui/react';
import Modal from '@/Components/Modal';
import { DangerButton, PrimaryButton, SecondaryButton } from '@/Components/FormControls';

/** A confirm/cancel dialog. `title` labels the dialog and `children` describe it. */
export default function ConfirmationModal({
    show = false,
    onClose,
    onConfirm,
    title,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    processing = false,
    processingLabel = 'Processing…',
    variant = 'confirm',
    maxWidth = 'md',
    children,
}) {
    const ConfirmButton = variant === 'delete' ? DangerButton : PrimaryButton;

    return (
        <Modal show={show} onClose={onClose} maxWidth={maxWidth}>
            <div className="p-6 text-white">
                <DialogTitle as="h2" className="mb-2 text-lg font-semibold">
                    {title}
                </DialogTitle>
                <Description as="div" className="mb-6 text-sm text-gray-400">
                    {children}
                </Description>
                <div className="flex justify-end gap-3">
                    <SecondaryButton onClick={onClose} disabled={processing}>
                        {cancelLabel}
                    </SecondaryButton>
                    <ConfirmButton onClick={onConfirm} processing={processing}>
                        {processing ? processingLabel : confirmLabel}
                    </ConfirmButton>
                </div>
            </div>
        </Modal>
    );
}
