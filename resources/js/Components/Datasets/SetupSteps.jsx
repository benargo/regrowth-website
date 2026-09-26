import ToolNav, { ToolNavSteps } from "@/Components/ToolNav";

/** The wizard's first step: the dataset's own details form. */
export const DETAILS_STEP = { value: "details", label: "Core details" };

/** The wizard's last step: a summary of everything entered. */
export const REVIEW_STEP = { value: "review", label: "Review" };

/**
 * The wizard progress trail, shown in the ToolNav bar. `steps` are the
 * relationship steps between the details and review steps. `stepHref`
 * returns each step's URL, or null to show it as plain text, as before the
 * record exists.
 */
export default function SetupSteps({ steps, currentStep, stepHref = () => null }) {
    const allSteps = [DETAILS_STEP, ...steps, REVIEW_STEP].map((step) => ({
        key: step.value,
        label: step.label,
        href: stepHref(step),
    }));

    return (
        <ToolNav>
            <ToolNavSteps label="Setup progress" steps={allSteps} currentKey={currentStep} />
        </ToolNav>
    );
}
