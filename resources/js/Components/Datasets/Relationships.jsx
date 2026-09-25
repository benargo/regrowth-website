import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/react";
import Icon from "@/Components/FontAwesome/Icon";

/**
 * One relationship section on a dataset's Edit or Setup page. The id doubles
 * as the in-page anchor and matches the setup step slug.
 */
export default function Relationships({ id, title, description, children }) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-heading`}
            className="border-ink-600/40 flex scroll-mt-48 flex-col gap-6 border-t pt-8 lg:scroll-mt-28"
        >
            <header className="flex flex-col gap-1">
                <h2 id={`${id}-heading`} className="font-serif text-2xl text-white">
                    {title}
                </h2>
                {description && <p className="text-secondary-300 max-w-prose text-sm">{description}</p>}
            </header>
            {children}
        </section>
    );
}

/**
 * A collapsed "Add a …" form, so the checklist stays the focus of the section.
 */
function AddRecord({ label, children }) {
    return (
        <Disclosure as="div" className="border-ink-600/40 rounded border">
            <DisclosureButton className="group data-focus:outline-ink-400 flex w-full items-center justify-between gap-4 px-4 py-3 text-left text-sm font-semibold text-white data-focus:outline-2 data-focus:outline-offset-2">
                <span className="flex items-center gap-2">
                    <Icon icon="plus" />
                    {label}
                </span>
                <Icon
                    icon="chevron-down"
                    className="transition-transform group-data-open:rotate-180 motion-reduce:transition-none"
                />
            </DisclosureButton>
            <DisclosurePanel className="border-ink-600/40 border-t p-4">{children}</DisclosurePanel>
        </Disclosure>
    );
}

/**
 * Render the section component for a setup step. `sections` is the dataset's
 * import.meta.glob of its {Name}Section.jsx files, so adding a step needs only
 * a new step and a matching section file. Every other prop is passed through
 * to the section.
 */
function Step({ step, sections, ...sectionProps }) {
    const path = Object.keys(sections).find((key) => key.endsWith(`/${step.component}.jsx`));

    if (!path) {
        throw new Error(`The "${step.value}" step has no ${step.component}.jsx section component.`);
    }

    const Section = sections[path];

    return <Section {...sectionProps} />;
}

Relationships.AddRecord = AddRecord;
Relationships.Step = Step;
