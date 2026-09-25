import { Link, useForm, usePage } from "@inertiajs/react";
import AutoSaveLabel from "@/Components/AutoSaveLabel";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import EditLockGuard from "@/Components/GameVersions/EditLockGuard";
import OnThisPage from "@/Components/OnThisPage";
import GameVersionForm, { gameVersionFormData } from "@/Components/GameVersions/GameVersionForm";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav from "@/Components/ToolNav";
import Relationships from "@/Datasets/Relationships";
import { AutosaveProvider } from "@/Hooks/useAutosave";
import Master from "@/Layouts/Master";

/**
 * Every {Name}Section.jsx game version section, which Relationships.Step picks
 * from by the step's component name (GameVersionSetupStep::component()).
 */
const sections = import.meta.glob("/resources/js/Components/GameVersions/*Section.jsx", {
    eager: true,
    import: "default",
});

const DETAILS_SECTION = { value: "details", label: "Details" };
const AUTOSAVE_HINT = "Changes will save automatically.";

export default function Edit({ gameVersion, options, relationships, steps, canEdit, editor }) {
    const form = useForm(gameVersionFormData(gameVersion));
    const { url } = usePage();
    const openedFromReview = new URLSearchParams(url.split("?")[1]?.split("#")[0]).has("review");

    return (
        <Master title={`Edit ${gameVersion.title}`}>
            <SharedHeader backgroundClass={gameVersion.banner_class} title={gameVersion.title} />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <Link
                        href={route("management.game-versions.index")}
                        className="hover:border-primary hover:bg-ground-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to game versions
                    </Link>
                </div>
            </ToolNav>

            <PageContainer>
                <AutosaveProvider>
                    <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-12">
                        <OnThisPage sections={[DETAILS_SECTION, ...steps]} className="lg:w-1/4 lg:shrink-0">
                            <div className="flex flex-col gap-1 lg:pl-4">
                                <div role="status" aria-live="polite" className="flex items-center lg:min-h-6">
                                    <AutoSaveLabel errorMessage="Couldn't save. Fix the highlighted fields." />
                                </div>
                                <p className="text-secondary-300 hidden text-sm lg:block">{AUTOSAVE_HINT}</p>
                            </div>
                        </OnThisPage>

                        <div className="flex min-w-0 flex-1 flex-col gap-10">
                            {openedFromReview && (
                                <Link
                                    href={route("management.game-versions.review", gameVersion.id)}
                                    className="text-secondary-300 focus-visible:outline-ink-400 flex items-center gap-2 self-start rounded text-sm underline hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                                >
                                    <Icon icon="arrow-left" style="solid" className="text-xs" />
                                    Back to review
                                </Link>
                            )}

                            <p className="text-secondary-300 -mt-6 text-sm lg:hidden">{AUTOSAVE_HINT}</p>

                            <EditLockGuard canEdit={canEdit} editor={editor}>
                                <section
                                    id="details"
                                    aria-labelledby="details-heading"
                                    className="flex scroll-mt-48 flex-col gap-6 lg:scroll-mt-28"
                                >
                                    <h2 id="details-heading" className="font-serif text-2xl text-white">
                                        Details
                                    </h2>
                                    <GameVersionForm
                                        form={form}
                                        options={options}
                                        autosave={{
                                            url: route("management.game-versions.update", gameVersion.id),
                                            saved: gameVersionFormData(gameVersion),
                                        }}
                                    />
                                </section>

                                {steps.map((step) => (
                                    <Relationships.Step
                                        key={step.value}
                                        step={step}
                                        sections={sections}
                                        gameVersion={gameVersion}
                                        relationships={relationships}
                                        autosave
                                    />
                                ))}
                            </EditLockGuard>
                        </div>
                    </div>
                </AutosaveProvider>
            </PageContainer>
        </Master>
    );
}
