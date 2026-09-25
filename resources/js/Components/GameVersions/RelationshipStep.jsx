/**
 * Every {Name}Section.jsx beside this file, keyed by path. A step names its
 * section (GameVersionSetupStep::component()), so adding a step needs only a
 * new enum case and a matching section file, no change here.
 */
const sections = import.meta.glob("./*Section.jsx", { eager: true, import: "default" });

/**
 * Render the section component for a setup step. Every section takes the same
 * props: gameVersion, relationships, and optionally submitLabel and onSaved.
 */
export default function RelationshipStep({ step, ...sectionProps }) {
    const Section = sections[`./${step.component}.jsx`];

    if (!Section) {
        throw new Error(`The "${step.value}" step has no ${step.component}.jsx section component.`);
    }

    return <Section {...sectionProps} />;
}
