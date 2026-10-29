import { useForm } from "@inertiajs/react";
import BossForm, { bossFormData } from "@/Components/Bosses/BossForm";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

export default function Create({ raidId, from, options, routes }) {
    const form = useForm(bossFormData(null, raidId, from));

    return (
        <Master title="Add a boss">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Add a boss" />

            <PageContainer>
                <BossForm
                    form={form}
                    options={options}
                    onSubmit={(visitOptions) => form.post(routes.store, visitOptions)}
                    submitLabel="Add boss"
                    processingLabel="Adding…"
                    cancelHref={routes.cancel}
                />
            </PageContainer>
        </Master>
    );
}
