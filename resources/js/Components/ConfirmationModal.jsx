import Modal from '@/Components/Modal';
import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

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
                <h2 className="mb-2 text-lg font-semibold">{title}</h2>
                <div className="mb-6 text-sm text-gray-400">{children}</div>
                <div className="flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing}>
                        {cancelLabel}
                    </SecondaryButton>
                    <ConfirmButton type="button" onClick={onConfirm} disabled={processing}>
                        {processing ? processingLabel : confirmLabel}
                    </ConfirmButton>
                </div>
            </div>
        </Modal>
    );
}
