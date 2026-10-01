import { Link } from "@inertiajs/react";
import { linkClassName } from "@/Components/FormControls";

/**
 * The submit label and skip link text for a setup step: back to the review
 * page when the step was opened from there, otherwise onwards to the next
 * step or the review.
 */
export function setupNavigationLabels(nextStep, returnToReview) {
    if (returnToReview) {
        return { submit: "Save and return to review", skip: "Back to review" };
    }

    if (nextStep) {
        return { submit: "Save and continue", skip: `Skip to ${nextStep.label.toLowerCase()}` };
    }

    return { submit: "Save and review", skip: "Skip to review" };
}

/**
 * The back link and onward action at the foot of a setup wizard page.
 */
export default function SetupNavigation({ backHref, backLabel, children }) {
    return (
        <nav
            aria-label="Setup navigation"
            className="border-ink-600/40 flex flex-wrap items-center justify-between gap-4 border-t pt-6"
        >
            <Link href={backHref} className={linkClassName}>
                {backLabel}
            </Link>
            {children}
        </nav>
    );
}
