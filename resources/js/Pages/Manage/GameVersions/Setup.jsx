import { Link, router } from "@inertiajs/react";
import EditLockGuard from "@/Components/Datasets/EditLockGuard";
import Relationships from "@/Components/Datasets/Relationships";
import SetupNavigation, { setupNavigationLabels } from "@/Components/Datasets/SetupNavigation";
import { linkClassName } from "@/Components/FormControls";
import SetupSteps from "@/Components/GameVersions/SetupSteps";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

/**
 * Every {Name}Section.jsx game version section, which Relationships.Step picks
 * from by the step's component name (GameVersionSetupStep::component()).
 */
const sections = import.meta.glob("/resources/js/Components/GameVersions/*Section.jsx", {
    eager: true,
    import: "default",
});

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
    const labels = setupNavigationLabels(nextStep, returnToReview);

    return (
        <Master title={`Set up ${gameVersion.title}`}>
            <SharedHeader backgroundClass={gameVersion.banner_class} title={`Set up ${gameVersion.title}`} />

            <SetupSteps gameVersion={gameVersion} steps={steps} currentStep={step.value} />

            <PageContainer>
                <div className="mx-auto flex max-w-5xl flex-col gap-8">
                    <EditLockGuard canEdit={canEdit} editor={editor} recordName="game version">
                        <Relationships.Step
                            step={step}
                            sections={sections}
                            gameVersion={gameVersion}
                            relationships={relationships}
                            submitLabel={labels.submit}
                            onSaved={() => router.visit(nextUrl)}
                        />
                    </EditLockGuard>

                    <SetupNavigation
                        backHref={previousUrl}
                        backLabel={previousStep ? `Back to ${previousStep.label.toLowerCase()}` : "Back to details"}
                    >
                        <Link href={nextUrl} className={linkClassName}>
                            {labels.skip}
                        </Link>
                    </SetupNavigation>
                </div>
            </PageContainer>
        </Master>
    );
}
