import { Link, usePage } from "@inertiajs/react";
import AutoSaveLabel from "@/Components/AutoSaveLabel";
import Relationships from "@/Components/Datasets/Relationships";
import { DETAILS_STEP } from "@/Components/Datasets/SetupSteps";
import Icon from "@/Components/FontAwesome/Icon";
import OnThisPage from "@/Components/OnThisPage";
import { AutosaveProvider } from "@/Hooks/useAutosave";

const AUTOSAVE_HINT = "Changes will save automatically.";

/**
 * Whether the page was opened from the setup review page (?review in the URL).
 */
function useOpenedFromReview() {
    const { url } = usePage();

    return new URLSearchParams(url.split("?")[1]?.split("#")[0]).has("review");
}

/**
 * The URL with ?review added, so the page it opens leads back to the setup
 * review page. Any #anchor is kept.
 */
export function withReview(url) {
    const [address, anchor] = url.split("#");
    const reviewUrl = new URL(address, window.location.origin);
    reviewUrl.searchParams.set("review", "1");

    return anchor ? `${reviewUrl}#${anchor}` : reviewUrl.toString();
}

/**
 * A dataset's autosaving Edit page body: an "On this page" sidebar with the
 * save status, beside the details and relationship sections. `steps` are the
 * relationship steps after the details section. When the page was opened
 * from the setup review page, a link leads back to `routes.review`.
 */
export default function EditLayout({ steps, routes, children }) {
    const reviewHref = useOpenedFromReview() ? routes.review : null;

    return (
        <AutosaveProvider>
            <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-12">
                <OnThisPage
                    sections={[{ ...DETAILS_STEP, label: "Details" }, ...steps]}
                    className="lg:w-1/4 lg:shrink-0"
                >
                    <div className="flex flex-col gap-1 lg:pl-4">
                        <div role="status" aria-live="polite" className="flex items-center lg:min-h-6">
                            <AutoSaveLabel errorMessage="Couldn't save. Fix the highlighted fields." />
                        </div>
                        <p className="text-secondary-300 hidden text-sm lg:block">{AUTOSAVE_HINT}</p>
                    </div>
                </OnThisPage>

                <div className="flex min-w-0 flex-1 flex-col gap-10">
                    {reviewHref && (
                        <Link
                            href={reviewHref}
                            className="text-secondary-300 focus-visible:outline-ink-400 flex items-center gap-2 self-start rounded text-sm underline hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            <Icon icon="arrow-left" style="solid" className="text-xs" />
                            Back to review
                        </Link>
                    )}

                    <p className="text-secondary-300 -mt-6 text-sm lg:hidden">{AUTOSAVE_HINT}</p>

                    {children}
                </div>
            </div>
        </AutosaveProvider>
    );
}

/**
 * The Edit page's details section, which the "On this page" sidebar links to.
 */
function Details({ children }) {
    return (
        <section
            id={DETAILS_STEP.value}
            aria-labelledby={`${DETAILS_STEP.value}-heading`}
            className="flex scroll-mt-48 flex-col gap-6 lg:scroll-mt-28"
        >
            <h2 id={`${DETAILS_STEP.value}-heading`} className="font-serif text-2xl text-white">
                Details
            </h2>
            {children}
        </section>
    );
}

EditLayout.Details = Details;

/**
 * Every relationship step's section, autosaving. `sections` is the dataset's
 * import.meta.glob of its {Name}Section.jsx files; every other prop is passed
 * through to each section.
 */
function Steps({ steps, sections, ...sectionProps }) {
    return steps.map((step) => (
        <Relationships.Step key={step.value} step={step} sections={sections} {...sectionProps} autosave />
    ));
}

EditLayout.Steps = Steps;
