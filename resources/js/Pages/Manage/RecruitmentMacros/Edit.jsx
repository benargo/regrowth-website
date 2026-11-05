import { useState } from "react";
import { router } from "@inertiajs/react";
import ConfirmationModal from "@/Components/ConfirmationModal";
import Icon from "@/Components/FontAwesome/Icon";
import { DangerButton } from "@/Components/FormControls";
import PageContainer from "@/Components/PageContainer";
import MacroForm from "@/Components/RecruitmentMacros/MacroForm";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

export default function Edit({ macro: macroResource, types, mentions }) {
    const macro = macroResource.data;
    const [isConfirmingDelete, setIsConfirmingDelete] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    function deleteMacro() {
        setIsDeleting(true);
        router.delete(route("management.recruitment-macros.destroy", macro.id), {
            onFinish: () => {
                setIsDeleting(false);
                setIsConfirmingDelete(false);
            },
        });
    }

    return (
        <Master title={`Edit ${macro.title}`}>
            <SharedHeader backgroundClass="bg-officer-meeting" title={macro.title} />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={route("management.recruitment-macros.index")}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to recruitment macros
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <MacroForm
                    key={macro.id}
                    macro={macro}
                    types={types}
                    mentions={mentions}
                    submitLabel="Save macro"
                    processingLabel="Saving…"
                />

                {macro.meta.can_delete && (
                    <section
                        aria-labelledby="delete-macro-heading"
                        className="mt-12 flex flex-col gap-3 border-t border-amber-600/40 pt-6"
                    >
                        <h2 id="delete-macro-heading" className="font-serif text-2xl text-white">
                            Delete this macro
                        </h2>
                        <p className="text-sm text-gray-300">
                            It moves to Recently deleted, where you can restore it for 30 days.
                        </p>
                        <div>
                            <DangerButton onClick={() => setIsConfirmingDelete(true)}>Delete macro</DangerButton>
                        </div>
                    </section>
                )}
            </PageContainer>

            <ConfirmationModal
                show={isConfirmingDelete}
                onClose={() => setIsConfirmingDelete(false)}
                onConfirm={deleteMacro}
                title={`Delete ${macro.title}?`}
                confirmLabel="Delete"
                processingLabel="Deleting…"
                processing={isDeleting}
                variant="delete"
            >
                Officers stop seeing it straight away. You can restore it from Recently deleted for 30 days.
            </ConfirmationModal>
        </Master>
    );
}
