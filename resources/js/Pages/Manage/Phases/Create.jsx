import { useForm } from "@inertiajs/react";
import PageContainer from "@/Components/PageContainer";
import PhaseForm, { phaseFormData } from "@/Components/Phases/PhaseForm";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

export default function Create({ gameVersionId, options, routes }) {
    const form = useForm(phaseFormData(null, gameVersionId));

    return (
        <Master title="Add a phase">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Add a phase" />

            <PageContainer>
                <PhaseForm
                    form={form}
                    options={options}
                    onSubmit={(visitOptions) => form.post(routes.store, visitOptions)}
                    submitLabel="Add phase"
                    processingLabel="Adding…"
                    cancelHref={routes.index}
                />
            </PageContainer>
        </Master>
    );
}
