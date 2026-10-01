import { useForm } from "@inertiajs/react";
import EditLayout from "@/Components/Datasets/EditLayout";
import EditLockGuard from "@/Components/Datasets/EditLockGuard";
import Icon from "@/Components/FontAwesome/Icon";
import GameVersionForm, { gameVersionFormData } from "@/Components/GameVersions/GameVersionForm";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

/**
 * Every {Name}Section.jsx game version section, which Relationships.Step picks
 * from by the step's component name (GameVersionSetupStep::component()).
 */
const sections = import.meta.glob("/resources/js/Components/GameVersions/*Section.jsx", {
    eager: true,
    import: "default",
});

export default function Edit({ gameVersion, options, relationships, steps, routes, canEdit, editor }) {
    const form = useForm(gameVersionFormData(gameVersion));

    return (
        <Master title={`Edit ${gameVersion.title}`}>
            <SharedHeader backgroundClass={gameVersion.banner_class} title={gameVersion.title} />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={routes.index}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to game versions
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <EditLayout steps={steps} routes={routes}>
                    <EditLockGuard canEdit={canEdit} editor={editor} recordName="game version">
                        <EditLayout.Details>
                            <GameVersionForm
                                form={form}
                                options={options}
                                autosave={{ url: routes.update, saved: gameVersionFormData(gameVersion) }}
                            />
                        </EditLayout.Details>

                        <EditLayout.Steps
                            steps={steps}
                            sections={sections}
                            gameVersion={gameVersion}
                            relationships={relationships}
                            routes={routes}
                        />
                    </EditLockGuard>
                </EditLayout>
            </PageContainer>
        </Master>
    );
}
