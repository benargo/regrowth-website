import DatasetSetupSteps, { DETAILS_STEP, REVIEW_STEP } from "@/Components/Datasets/SetupSteps";

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
 * The game version wizard's progress trail. Before the game version exists
 * (the Create page) the steps are plain text; afterwards every step is a link.
 */
export default function SetupSteps({ gameVersion = null, steps, currentStep }) {
    return (
        <DatasetSetupSteps steps={steps} currentStep={currentStep} stepHref={(step) => stepHref(gameVersion, step)} />
    );
}
