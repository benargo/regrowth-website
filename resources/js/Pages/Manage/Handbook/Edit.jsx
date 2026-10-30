import { useState } from "react";
import { router } from "@inertiajs/react";
import ConfirmationModal from "@/Components/ConfirmationModal";
import Icon from "@/Components/FontAwesome/Icon";
import { DangerButton } from "@/Components/FormControls";
import PageContainer from "@/Components/PageContainer";
import ChapterForm from "@/Components/Handbook/ChapterForm";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

export default function Edit({ chapter, mentions }) {
    const [isConfirmingDelete, setIsConfirmingDelete] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    function deleteChapter() {
        setIsDeleting(true);
        router.delete(route("management.handbook.destroy", chapter.slug), {
            onFinish: () => {
                setIsDeleting(false);
                setIsConfirmingDelete(false);
            },
        });
    }

    return (
        <Master title={`Edit ${chapter.title}`}>
            <SharedHeader backgroundClass="bg-officer-meeting" title={chapter.title} />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={route("management.handbook.index")}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to raid handbook
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <ChapterForm
                    chapter={chapter}
                    mentions={mentions}
                    submitLabel="Save chapter"
                    processingLabel="Saving…"
                />

                {!chapter.is_introduction && (
                    <section
                        aria-labelledby="delete-chapter-heading"
                        className="mt-12 flex flex-col gap-3 border-t border-amber-600/40 pt-6"
                    >
                        <h2 id="delete-chapter-heading" className="font-serif text-2xl text-white">
                            Delete this chapter
                        </h2>
                        <p className="text-sm text-gray-300">
                            It moves to Recently deleted, where you can restore it for 30 days.
                        </p>
                        <div>
                            <DangerButton onClick={() => setIsConfirmingDelete(true)}>Delete chapter</DangerButton>
                        </div>
                    </section>
                )}
            </PageContainer>

            <ConfirmationModal
                show={isConfirmingDelete}
                onClose={() => setIsConfirmingDelete(false)}
                onConfirm={deleteChapter}
                title={`Delete ${chapter.title}?`}
                confirmLabel="Delete"
                processingLabel="Deleting…"
                processing={isDeleting}
                variant="delete"
            >
                Members stop seeing it straight away. You can restore it from Recently deleted for 30 days.
            </ConfirmationModal>
        </Master>
    );
}
