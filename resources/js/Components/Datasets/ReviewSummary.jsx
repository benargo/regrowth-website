import { Link } from "@inertiajs/react";
import { withReview } from "@/Components/Datasets/EditLayout";
import ReviewSection from "@/Components/Datasets/ReviewSection";
import SetupNavigation from "@/Components/Datasets/SetupNavigation";
import Icon from "@/Components/FontAwesome/Icon";
import { linkButtonClassName } from "@/Components/FormControls";

/**
 * A dataset's setup review page body: the details, then a summary of each
 * step, each with an Edit link that returns here once saved. `details` are
 * [label, value] pairs. `summaries` maps each step value to a function that
 * builds that step's linked records lists ({ heading?, items }) from the
 * relationships.
 */
export default function ReviewSummary({ routes, steps, details, summaries, relationships }) {
    const lastStep = steps[steps.length - 1];

    return (
        <div className="mx-auto flex max-w-5xl flex-col gap-8">
            <p className="text-secondary-300 max-w-prose">
                Check the details and linked records below. Use Edit to go back to a step; saving it brings you back
                here.
            </p>

            <ReviewSection
                id="details"
                title="Details"
                editHref={withReview(`${routes.edit}#details`)}
                editLabel="Edit details"
            >
                <ReviewSection.Details details={details} />
            </ReviewSection>

            {steps.map((step) => {
                const summarise = summaries[step.value];

                if (!summarise) {
                    throw new Error(`The "${step.value}" step has no summary on the review page.`);
                }

                return (
                    <ReviewSection
                        key={step.value}
                        id={step.value}
                        title={step.label}
                        editHref={withReview(step.href)}
                        editLabel={`Edit ${step.label.toLowerCase()}`}
                    >
                        {summarise(relationships).map((list, index) => (
                            <ReviewSection.Records
                                key={list.heading ?? index}
                                heading={list.heading}
                                items={list.items}
                            />
                        ))}
                    </ReviewSection>
                );
            })}

            <SetupNavigation backHref={lastStep.href} backLabel={`Back to ${lastStep.label.toLowerCase()}`}>
                <Link href={routes.index} className={linkButtonClassName}>
                    <Icon icon="check" />
                    Finish
                </Link>
            </SetupNavigation>
        </div>
    );
}
