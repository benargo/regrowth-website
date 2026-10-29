import { useForm } from "@inertiajs/react";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";
import FormContainer from "@/Components/FormContainer";
import { FormRow, SaveButton, TextInput } from "@/Components/FormControls";

export default function LocalLogin() {
    const { data, setData, post, processing, errors } = useForm({
        id: "",
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route("login.local.store"));
    }

    return (
        <Master title="Local Login">
            <SharedHeader title="Local Login" subtitle="Development sign-in — local and testing only" />

            <FormContainer maxWidth="lg">
                <p className="text-gray-400 mb-6 text-sm">
                    Enter the Discord ID of an existing user to sign in as them. This bypasses Discord OAuth and is only
                    available outside production.
                </p>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <FormRow label="User ID" error={errors.id}>
                        <TextInput
                            value={data.id}
                            onChange={(e) => setData("id", e.target.value)}
                            placeholder="e.g. 123456789012345678"
                            invalid={!!errors.id}
                            autoFocus
                        />
                    </FormRow>

                    <SaveButton processing={processing} label="Sign in" processingLabel="Signing in…" />
                </form>
            </FormContainer>
        </Master>
    );
}
