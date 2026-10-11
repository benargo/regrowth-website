import OnThisPage from "@/Components/OnThisPage";

/**
 * The absence form's sections, linked from the "On this page" sidebar. Each
 * `value` is the id of its FormSection.
 */
export const ABSENCE_SECTIONS = [
    { value: "whos-away", label: "Who's away" },
    { value: "the-absence", label: "The absence" },
];

/**
 * The absence form page body, laid out like a dataset's Edit page: an
 * "On this page" sidebar with an optional `note` beneath its links, beside
 * the form's details section.
 */
export default function AbsenceFormLayout({ note = null, children }) {
    return (
        <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-12">
            <OnThisPage sections={ABSENCE_SECTIONS} className="lg:w-1/4 lg:shrink-0">
                {note && <p className="text-secondary-300 hidden text-sm lg:block lg:pl-4">{note}</p>}
            </OnThisPage>

            <div className="flex min-w-0 flex-1 flex-col gap-10">
                {note && <p className="text-secondary-300 -mt-6 text-sm lg:hidden">{note}</p>}

                <section aria-labelledby="details-heading" className="flex flex-col gap-6">
                    <h2 id="details-heading" className="font-serif text-2xl text-white">
                        Details
                    </h2>
                    {children}
                </section>
            </div>
        </div>
    );
}
