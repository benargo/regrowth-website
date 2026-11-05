import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import MacroForm from "@/Components/RecruitmentMacros/MacroForm";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

export default function Create({ types, mentions }) {
    return (
        <Master title="New recruitment macro">
            <SharedHeader backgroundClass="bg-officer-meeting" title="New recruitment macro" />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={route("management.recruitment-macros.index")}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to recruitment macros
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <MacroForm types={types} mentions={mentions} submitLabel="Add macro" processingLabel="Adding…" />
            </PageContainer>
        </Master>
    );
}
