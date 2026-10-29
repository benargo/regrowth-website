import { useForm } from "@inertiajs/react";
import SetupSteps from "@/Components/Datasets/SetupSteps";
import GameVersionForm, { gameVersionFormData } from "@/Components/GameVersions/GameVersionForm";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

export default function Create({ options, steps, routes }) {
    const form = useForm(gameVersionFormData());

    return (
        <Master title="Add a game version">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Add a game version" />

            <SetupSteps steps={steps} currentStep="details" />

            <PageContainer>
                <div className="flex flex-col gap-8">
                    <GameVersionForm
                        form={form}
                        options={options}
                        onSubmit={(visitOptions) => form.post(routes.store, visitOptions)}
                        submitLabel="Save and continue"
                        processingLabel="Saving…"
                        cancelHref={routes.index}
                    />
                </div>
            </PageContainer>
        </Master>
    );
}
