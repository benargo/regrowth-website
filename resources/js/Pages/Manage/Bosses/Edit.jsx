import { useMemo } from "react";
import { useForm } from "@inertiajs/react";
import BossForm, { bossFormData } from "@/Components/Bosses/BossForm";
import BossStrategyFields from "@/Components/Bosses/BossStrategyFields";
import EditLayout from "@/Components/Datasets/EditLayout";
import EditLockGuard from "@/Components/Datasets/EditLockGuard";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

const SECTIONS = [{ value: "strategy", label: "Strategy" }];

export default function Edit({ boss: { data: boss }, routes, canEdit, editor }) {
    const saved = useMemo(() => bossFormData(boss), [boss]);
    const form = useForm(saved);
    const { raid } = boss;

    return (
        <Master title={`Edit ${boss.name}`}>
            <SharedHeader
                backgroundClass="bg-officer-meeting"
                title={boss.name}
                subtitle={raid.name}
                image={raid.banner_url}
                imagePosition={raid.banner_position}
                color={raid.color}
            />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={routes.index}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to bosses
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <EditLayout steps={SECTIONS} routes={routes}>
                    <EditLockGuard canEdit={canEdit} editor={editor} recordName="boss">
                        <EditLayout.Details>
                            <BossForm
                                form={form}
                                raid={{ name: raid.name, href: routes.raid }}
                                autosave={{ url: routes.update, saved }}
                            />
                        </EditLayout.Details>
                        <BossStrategyFields boss={boss} url={routes.update} canEdit={canEdit} />
                    </EditLockGuard>
                </EditLayout>
            </PageContainer>
        </Master>
    );
}
