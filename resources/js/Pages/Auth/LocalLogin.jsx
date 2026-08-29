import { useForm } from "@inertiajs/react";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";
import FormContainer from "@/Components/FormContainer";
import FormField from "@/Components/FormField";
import TextInput from "@/Components/TextInput";
import PrimaryButton from "@/Components/PrimaryButton";

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
                <p className="mb-6 text-sm text-gray-400">
                    Enter the Discord ID of an existing user to sign in as them. This bypasses Discord OAuth and is only
                    available outside production.
                </p>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <FormField label="User ID" htmlFor="id" error={errors.id}>
                        <TextInput
                            id="id"
                            type="text"
                            value={data.id}
                            onChange={(e) => setData("id", e.target.value)}
                            placeholder="e.g. 123456789012345678"
                            isFocused
                            className="w-full"
                        />
                    </FormField>

                    <div>
                        <PrimaryButton type="submit" processing={processing}>
                            {processing ? "Signing in…" : "Sign in"}
                        </PrimaryButton>
                    </div>
                </form>
            </FormContainer>
        </Master>
    );
}
