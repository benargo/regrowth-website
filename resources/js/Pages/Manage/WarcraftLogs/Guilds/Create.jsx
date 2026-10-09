import { useForm } from "@inertiajs/react";
import DetailsForm from "@/Components/Datasets/DetailsForm";
import { FormRow, FormSection } from "@/Components/FormControls";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

export default function Create({ namespaces }) {
    const form = useForm({ id: "", namespace: "" });
    const { errors } = form;

    return (
        <Master title="Add a Warcraft Logs guild">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Add a Warcraft Logs guild" />

            <PageContainer>
                <DetailsForm
                    form={form}
                    onSubmit={(visitOptions) => form.post(route("management.warcraftlogs.guilds.store"), visitOptions)}
                    submitLabel="Add guild"
                    processingLabel="Adding…"
                    cancelHref={route("management.warcraftlogs.guilds.index")}
                >
                    {({ text, select }) => (
                        <FormSection legend="The guild">
                            <FormRow
                                htmlFor="id"
                                label="Warcraft Logs guild ID"
                                required
                                hint="The number in the guild's Warcraft Logs page address, such as 774848. It can't be changed later."
                                error={errors.id}
                            >
                                {text("id", { type: "number", min: 1, inputMode: "numeric", required: true, autoComplete: "off" })}
                            </FormRow>
                            <FormRow
                                htmlFor="namespace"
                                label="Warcraft Logs site"
                                required
                                hint="Which Warcraft Logs site the guild's reports are on. Its tags are fetched as soon as you add it."
                                error={errors.namespace}
                            >
                                {select("namespace", { options: namespaces, placeholder: "Choose a site", required: true })}
                            </FormRow>
                        </FormSection>
                    )}
                </DetailsForm>
            </PageContainer>
        </Master>
    );
}
