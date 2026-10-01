import SetupSteps from "@/Components/Datasets/SetupSteps";
import SetupWizardStep from "@/Components/Datasets/SetupWizardStep";
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
    routes,
    canEdit,
    editor,
}) {
    return (
        <Master title={`Set up ${gameVersion.title}`}>
            <SharedHeader backgroundClass={gameVersion.banner_class} title={`Set up ${gameVersion.title}`} />

            <SetupSteps steps={steps} currentStep={step.value} routes={routes} />

            <PageContainer>
                <SetupWizardStep
                    routes={routes}
                    step={step}
                    previousStep={previousStep}
                    nextStep={nextStep}
                    returnToReview={returnToReview}
                    canEdit={canEdit}
                    editor={editor}
                    recordName="game version"
                    sections={sections}
                    sectionProps={{ gameVersion, relationships }}
                />
            </PageContainer>
        </Master>
    );
}
