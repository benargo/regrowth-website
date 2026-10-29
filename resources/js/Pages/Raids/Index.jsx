import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ImageCard from "@/Components/ImageCard";
import EmptyState from "@/Components/EmptyState";
import SectionNav from "@/Components/Navigation/SectionNav";
import { SITE_SECTIONS } from "@/Helpers/siteSections";

export default function Index({ section, phases }) {
    const sectionConfig = SITE_SECTIONS[section];

    return (
        <Master title={`Raids - ${sectionConfig.label}`}>
            <SharedHeader title="Raids" subtitle={sectionConfig.label} backgroundClass={sectionConfig.headerBackgroundClass} />
            <SectionNav section={section} />
            <PageContainer>
                {phases.data.length === 0 ? (
                    <EmptyState icon="dungeon" message="Raids are not available for this game version." />
                ) : (
                    <div className="flex flex-col gap-12">
                        {phases.data.map((phase) => (
                            <section key={phase.id} aria-labelledby={`phase-${phase.id}-heading`}>
                                <h2 id={`phase-${phase.id}-heading`} className="mb-4 font-serif text-2xl text-amber-200">
                                    Phase {phase.number}
                                    {phase.name ? `: ${phase.name}` : ""}
                                </h2>
                                <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {phase.raids.map((raid) => (
                                        <li key={raid.id}>
                                            <ImageCard
                                                href={route(`${section}.raids.show`, { raid: raid.id, slug: raid.slug })}
                                                image={raid.banner_url}
                                                imagePosition={raid.banner_position}
                                                backgroundClass="bg-brown-900"
                                                color={raid.color}
                                                eyebrow={raid.difficulty}
                                                heading={raid.name}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </PageContainer>
        </Master>
    );
}
