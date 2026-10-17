import { useEffect, useRef, useState } from "react";
import OnThisPage from "@/Components/OnThisPage";

/**
 * A form page body: an "On this page" sidebar linking to `sections`, beside
 * the main column. `sidebar` renders beneath the sidebar's links. `note` is
 * a hint shown beneath the links on large screens and above the main column
 * on small ones.
 */
export default function FormPageLayout({ sections, sidebar = null, note = null, children }) {
    return (
        <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-12">
            <OnThisPage sections={sections} className="lg:w-1/4 lg:shrink-0">
                {(sidebar || note) && (
                    <div className="flex flex-col gap-1 lg:pl-4">
                        {sidebar}
                        {note && <p className="text-gray-300 hidden text-sm lg:block">{note}</p>}
                    </div>
                )}
            </OnThisPage>

            <div className="flex min-w-0 flex-1 flex-col gap-10">
                {note && <p className="text-gray-300 -mt-6 text-sm lg:hidden">{note}</p>}

                {children}
            </div>
        </div>
    );
}

/**
 * A titled section of a form page's main column. Give it an `id` when the
 * "On this page" sidebar links to it.
 */
export function FormPageSection({ id, title = "Details", children }) {
    const headingId = `${id ?? "details"}-heading`;

    return (
        <section id={id} aria-labelledby={headingId} className="flex scroll-mt-48 flex-col gap-6 lg:scroll-mt-28">
            <h2 id={headingId} className="font-serif text-2xl text-white">
                {title}
            </h2>
            {children}
        </section>
    );
}

/**
 * Moves focus to a form's ErrorSummary after each failed submit, so keyboard
 * and screen reader users land on it. Pass `summaryRef` to the ErrorSummary
 * and call `focusSummary` when a submit fails.
 */
export function useErrorSummaryFocus() {
    const summaryRef = useRef(null);
    const [failedSubmits, setFailedSubmits] = useState(0);

    // Focus after React commits the errors render, so the summary exists.
    useEffect(() => {
        if (failedSubmits > 0) {
            summaryRef.current?.focus();
        }
    }, [failedSubmits]);

    function focusSummary() {
        setFailedSubmits((count) => count + 1);
    }

    return { summaryRef, focusSummary };
}
