import SetupSteps from "@/Components/Datasets/SetupSteps";
import SetupWizardStep from "@/Components/Datasets/SetupWizardStep";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

/**
 * Every {Name}Section.jsx raid section, which Relationships.Step picks from
 * by the step's component name (RaidSetupStep::component()).
 */
const sections = import.meta.glob("/resources/js/Components/Raids/*Section.jsx", {
    eager: true,
    import: "default",
});

export default function Setup({
    raid: { data: raid },
    step,
    previousStep,
    nextStep,
    steps,
    returnToReview,
    routes,
    canEdit,
    editor,
}) {
    return (
        <Master title={`Set up ${raid.name}`}>
            <SharedHeader
                color={raid.color}
                image={raid.banner_url}
                imagePosition={raid.banner_position}
                backgroundClass="bg-officer-meeting"
                title={`Set up ${raid.name}`}
            />

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
                    recordName="raid"
                    sections={sections}
                    sectionProps={{ raid }}
                />
            </PageContainer>
        </Master>
    );
}
