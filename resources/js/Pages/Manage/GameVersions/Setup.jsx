import { Link, router } from "@inertiajs/react";
import { linkClassName } from "@/Components/FormControls";
import EditLockGuard from "@/Components/GameVersions/EditLockGuard";
import RelationshipStep from "@/Components/GameVersions/RelationshipStep";
import SetupSteps from "@/Components/GameVersions/SetupSteps";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

/**
 * The submit label and skip link text: back to the review page when the step
 * was opened from there, otherwise onwards to the next step or the review.
 */
function navigationLabels(nextStep, returnToReview) {
    if (returnToReview) {
        return { submit: "Save and return to review", skip: "Back to review" };
    }

    if (nextStep) {
        return { submit: "Save and continue", skip: `Skip to ${nextStep.label.toLowerCase()}` };
    }

    return { submit: "Save and review", skip: "Skip to review" };
}

export default function Setup({
    gameVersion,
    step,
    previousStep,
    nextStep,
    steps,
    relationships,
    returnToReview,
    canEdit,
    editor,
}) {
    const editUrl = route("management.game-versions.edit", gameVersion.id);
    const reviewUrl = route("management.game-versions.review", gameVersion.id);
    const previousUrl = previousStep
        ? route("management.game-versions.setup", [gameVersion.id, previousStep.value])
        : editUrl;
    const nextUrl =
        nextStep && !returnToReview
            ? route("management.game-versions.setup", [gameVersion.id, nextStep.value])
            : reviewUrl;
    const labels = navigationLabels(nextStep, returnToReview);

    return (
        <Master title={`Set up ${gameVersion.title}`}>
            <SharedHeader backgroundClass={gameVersion.banner_class} title={`Set up ${gameVersion.title}`} />

            <SetupSteps gameVersion={gameVersion} steps={steps} currentStep={step.value} />

            <PageContainer>
                <div className="mx-auto flex max-w-5xl flex-col gap-8">
                    <EditLockGuard canEdit={canEdit} editor={editor}>
                        <RelationshipStep
                            step={step}
                            gameVersion={gameVersion}
                            relationships={relationships}
                            submitLabel={labels.submit}
                            onSaved={() => router.visit(nextUrl)}
                        />
                    </EditLockGuard>

                    <nav
                        aria-label="Setup navigation"
                        className="border-ink-600/40 flex flex-wrap items-center justify-between gap-4 border-t pt-6"
                    >
                        <Link href={previousUrl} className={linkClassName}>
                            {previousStep ? `Back to ${previousStep.label.toLowerCase()}` : "Back to details"}
                        </Link>
                        <Link href={nextUrl} className={linkClassName}>
                            {labels.skip}
                        </Link>
                    </nav>
                </div>
            </PageContainer>
        </Master>
    );
}
