import { useForm } from "@inertiajs/react";
import SetupSteps from "@/Components/Datasets/SetupSteps";
import PageContainer from "@/Components/PageContainer";
import RaidForm, { raidFormData } from "@/Components/Raids/RaidForm";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

export default function Create({ phaseId, options, steps, routes }) {
    const form = useForm(raidFormData(null, phaseId));

    return (
        <Master title="Add a raid">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Add a raid" />

            <SetupSteps steps={steps} currentStep="details" />

            <PageContainer>
                <RaidForm
                    form={form}
                    options={options}
                    onSubmit={(visitOptions) => form.post(routes.store, visitOptions)}
                    submitLabel="Save and continue"
                    processingLabel="Saving…"
                    cancelHref={routes.index}
                />
            </PageContainer>
        </Master>
    );
}
