import ToolNav, { ToolNavSteps } from "@/Components/ToolNav";

const DETAILS_STEP = { value: "details", label: "Core details" };
const REVIEW_STEP = { value: "review", label: "Review" };

function stepHref(gameVersion, step) {
    if (!gameVersion) {
        return null;
    }

    if (step.value === DETAILS_STEP.value) {
        return route("management.game-versions.edit", gameVersion.id);
    }

    if (step.value === REVIEW_STEP.value) {
        return route("management.game-versions.review", gameVersion.id);
    }

    return route("management.game-versions.setup", [gameVersion.id, step.value]);
}

/**
 * The wizard progress trail, shown in the ToolNav bar. Before the game version
 * exists (the Create page) the steps are plain text; afterwards every step is
 * a link.
 */
export default function SetupSteps({ gameVersion = null, steps, currentStep }) {
    const allSteps = [DETAILS_STEP, ...steps, REVIEW_STEP].map((step) => ({
        key: step.value,
        label: step.label,
        href: stepHref(gameVersion, step),
    }));

    return (
        <ToolNav>
            <ToolNavSteps label="Setup progress" steps={allSteps} currentKey={currentStep} />
        </ToolNav>
    );
}
