import { useMemo } from "react";
import { useForm } from "@inertiajs/react";
import EditLayout from "@/Components/Datasets/EditLayout";
import EditLockGuard from "@/Components/Datasets/EditLockGuard";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import PhaseForm, { phaseFormData } from "@/Components/Phases/PhaseForm";
import PhaseRaids from "@/Components/Phases/PhaseRaids";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

export default function Edit({ phase: { data: phase }, options, routes, canEdit, editor }) {
    const saved = useMemo(() => phaseFormData(phase), [phase]);
    const form = useForm(saved);

    return (
        <Master title={`Edit ${phase.name}`}>
            <SharedHeader backgroundClass="bg-officer-meeting" title={phase.name} subtitle={phase.description} />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={routes.index}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to phases
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <EditLayout steps={[]} routes={routes}>
                    <EditLockGuard canEdit={canEdit} editor={editor} recordName="phase">
                        <EditLayout.Details>
                            <PhaseForm form={form} options={options} autosave={{ url: routes.update, saved }} />
                        </EditLayout.Details>
                        <PhaseRaids phase={phase} />
                    </EditLockGuard>
                </EditLayout>
            </PageContainer>
        </Master>
    );
}
