import { useEffect, useId, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import { DangerButton, SecondaryButton } from "@/Components/FormControls";

/**
 * A button that asks for confirmation inline before sending a DELETE request.
 *
 * Props:
 * @prop {string} href         - URL the DELETE request is sent to.
 * @prop {string} label        - Text of the initial button.
 * @prop {string} [ariaLabel]  - Accessible name of the initial button, when `label` alone is ambiguous.
 *                               It should start with `label`.
 * @prop {string} prompt       - Question shown in place of the button once it is clicked.
 * @prop {string} confirmLabel - Text of the button that sends the request.
 *
 * Behaviour:
 * - The first click swaps the button for the prompt with Confirm and Cancel buttons, and focuses Cancel.
 * - Confirm calls `router.delete(href, { preserveScroll: true })`; Cancel or Escape restores the button
 *   and focuses it again.
 */
export default function RevokeButton({ href, label, ariaLabel, prompt, confirmLabel }) {
    const [isConfirming, setIsConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const promptId = useId();
    const triggerRef = useRef(null);
    const cancelRef = useRef(null);
    const shouldRestoreFocus = useRef(false);

    useEffect(() => {
        if (isConfirming) {
            cancelRef.current?.focus();
        } else if (shouldRestoreFocus.current) {
            shouldRestoreFocus.current = false;
            triggerRef.current?.focus();
        }
    }, [isConfirming]);

    const cancel = () => {
        shouldRestoreFocus.current = true;
        setIsConfirming(false);
    };

    if (!isConfirming) {
        return (
            <SecondaryButton
                key="revoke-idle"
                ref={triggerRef}
                aria-label={ariaLabel}
                onClick={() => setIsConfirming(true)}
            >
                {label}
            </SecondaryButton>
        );
    }

    return (
        <div
            key="revoke-confirming"
            role="group"
            aria-labelledby={promptId}
            className="flex flex-wrap items-center gap-2"
            onKeyDown={(event) => {
                if (event.key === "Escape" && !processing) {
                    event.stopPropagation();
                    cancel();
                }
            }}
        >
            <span id={promptId} className="text-sm text-gray-300">
                {prompt}
            </span>
            <DangerButton
                processing={processing}
                onClick={() => {
                    setProcessing(true);
                    router.delete(href, {
                        preserveScroll: true,
                        onFinish: () => {
                            setProcessing(false);
                            setIsConfirming(false);
                        },
                    });
                }}
            >
                {confirmLabel}
            </DangerButton>
            <SecondaryButton ref={cancelRef} onClick={cancel} disabled={processing}>
                Cancel
            </SecondaryButton>
        </div>
    );
}
