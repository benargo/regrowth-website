import { useEffect, useMemo } from "react";
import { useForm } from "@inertiajs/react";
import EditLayout from "@/Components/Datasets/EditLayout";
import EditLockGuard from "@/Components/Datasets/EditLockGuard";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import RaidForm, { raidFormData } from "@/Components/Raids/RaidForm";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

/**
 * Every {Name}Section.jsx raid section, which Relationships.Step picks from
 * by the step's component name (RaidSetupStep::component()).
 */
const sections = import.meta.glob("/resources/js/Components/Raids/*Section.jsx", {
    eager: true,
    import: "default",
});

export default function Edit({ raid: { data: raid, media }, options, steps, routes, canEdit, editor }) {
    const saved = useMemo(() => raidFormData(raid), [raid]);
    const form = useForm(saved);
    const hasBanner = Boolean(raid.banner_url);

    /**
     * Bring the form in line with values the server cleared or re-cased: an
     * empty banner position once the banner is gone, and the stored difficulty
     * when it differs from the typed one only by case. Other fields, and
     * difficulties that differ by more than case, are left as typed.
     */
    useEffect(() => {
        if (!hasBanner && saved.banner_position === "" && form.data.banner_position !== "") {
            form.setData("banner_position", "");
        }

        if (
            form.data.difficulty !== saved.difficulty &&
            form.data.difficulty.toLowerCase() === saved.difficulty.toLowerCase()
        ) {
            form.setData("difficulty", saved.difficulty);
        }
    }, [hasBanner, saved.banner_position, saved.difficulty]);

    return (
        <Master title={`Edit ${raid.name}`}>
            <SharedHeader
                color={raid.color}
                image={raid.banner_url}
                imagePosition={raid.banner_position}
                backgroundClass="bg-officer-meeting"
                title={raid.name}
                subtitle={raid.difficulty}
            />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={routes.index}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to raids
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <EditLayout steps={steps} routes={routes}>
                    <EditLockGuard canEdit={canEdit} editor={editor} recordName="raid">
                        <EditLayout.Details>
                            <RaidForm
                                form={form}
                                options={options}
                                raid={raid}
                                media={media}
                                updateUrl={routes.update}
                                autosave={{ url: routes.update, saved }}
                            />
                        </EditLayout.Details>

                        <EditLayout.Steps steps={steps} sections={sections} raid={raid} routes={routes} />
                    </EditLockGuard>
                </EditLayout>
            </PageContainer>
        </Master>
    );
}
