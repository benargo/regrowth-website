import { Link, router } from "@inertiajs/react";
import EditLockGuard from "@/Components/Datasets/EditLockGuard";
import Relationships from "@/Components/Datasets/Relationships";
import SetupNavigation, { setupNavigationLabels } from "@/Components/Datasets/SetupNavigation";
import { linkClassName } from "@/Components/FormControls";

/**
 * One setup wizard step's body: the step's section behind the edit lock, then
 * the back and skip links. `routes` are the step's URLs from the server, with
 * `previous` and `next` already resolved; saving the section moves on to
 * `next`. `sectionProps` are passed through to the section, along with
 * `routes`.
 */
export default function SetupWizardStep({
    routes,
    step,
    previousStep,
    nextStep,
    returnToReview,
    canEdit,
    editor,
    recordName,
    sections,
    sectionProps,
}) {
    const labels = setupNavigationLabels(nextStep, returnToReview);

    return (
        <div className="mx-auto flex max-w-5xl flex-col gap-8">
            <EditLockGuard canEdit={canEdit} editor={editor} recordName={recordName}>
                <Relationships.Step
                    step={step}
                    sections={sections}
                    routes={routes}
                    {...sectionProps}
                    submitLabel={labels.submit}
                    onSaved={() => router.visit(routes.next)}
                />
            </EditLockGuard>

            <SetupNavigation
                backHref={routes.previous}
                backLabel={previousStep ? `Back to ${previousStep.label.toLowerCase()}` : "Back to details"}
            >
                <Link href={routes.next} className={linkClassName}>
                    {labels.skip}
                </Link>
            </SetupNavigation>
        </div>
    );
}
