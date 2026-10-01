import ToolNav, { ToolNavSteps } from "@/Components/ToolNav";

/** The wizard's first step: the dataset's own details form. */
export const DETAILS_STEP = { value: "details", label: "Core details" };

/** The wizard's last step: a summary of everything entered. */
export const REVIEW_STEP = { value: "review", label: "Review" };

/**
 * A step's URL: the details step is the Edit page, the review step is the
 * review page, and every other step carries its own setup page href.
 */
function stepHref(step, routes) {
    if (!routes) {
        return null;
    }

    if (step.value === DETAILS_STEP.value) {
        return routes.edit;
    }

    if (step.value === REVIEW_STEP.value) {
        return routes.review;
    }

    return step.href ?? null;
}

/**
 * The wizard progress trail, shown in the ToolNav bar. `steps` are the
 * relationship steps between the details and review steps. Before the record
 * exists (the Create page) there are no `routes` and every step is plain text;
 * afterwards every step is a link.
 */
export default function SetupSteps({ steps, currentStep, routes = null }) {
    const allSteps = [DETAILS_STEP, ...steps, REVIEW_STEP].map((step) => ({
        key: step.value,
        label: step.label,
        href: stepHref(step, routes),
    }));

    return (
        <ToolNav>
            <ToolNavSteps label="Setup progress" steps={allSteps} currentKey={currentStep} />
        </ToolNav>
    );
}
