import { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import Relationships from "@/Components/Datasets/Relationships";
import { FormRow, OptionSelect, SaveButton } from "@/Components/FormControls";
import ImageManager from "@/Components/ImageManager";
import { useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/** Each image the game version can have, keyed by its media collection. */
const IMAGES = [
    {
        key: "banner",
        label: "Banner",
        hint: "The wide header image shown at the top of this version's pages.",
    },
    {
        key: "logo",
        label: "Logo",
        hint: "The game's full-colour logo.",
    },
    {
        key: "icon",
        label: "Icon",
        hint: "A single-colour version of the logo, for small spaces such as menus.",
    },
];

/**
 * One image upload. Files are sent as soon as they are chosen, with the PATCH
 * spoofed over POST because browsers can't send files in a real PATCH.
 */
function ImageField({ image, url, routes }) {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    function send(method, data, options = {}) {
        router[method](routes.update, data, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onSuccess: () => setError(null),
            onError: (errors) => setError(errors[image.key] ?? null),
            onFinish: () => setSaving(false),
            ...options,
        });
    }

    return (
        <FormRow label={image.label} hint={image.hint}>
            <ImageManager
                images={url ? [url] : []}
                saving={saving}
                error={error}
                multiple={false}
                onUpload={([file]) => send("post", { _method: "patch", [image.key]: file }, { forceFormData: true })}
                onDelete={() => send("patch", { [image.key]: null })}
            />
        </FormRow>
    );
}

export default function ThemeSection({
    gameVersion,
    options,
    routes,
    submitLabel = "Save theme",
    onSaved,
    autosave = false,
}) {
    const saved = { theme: gameVersion.theme };
    const form = useForm(saved);
    const { schedule, containerProps } = useFormAutosave({
        form,
        url: routes.update,
        saved,
        key: "theme",
        trigger: "blur",
        delay: AUTOSAVE_DELAY,
        enabled: autosave,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.patch(routes.update, {
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
        });
    }

    return (
        <Relationships
            id="theme"
            title="Theme"
            description="Choose the site's colours for this version and upload its images. Images save as soon as you add or delete them."
        >
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8" {...containerProps}>
                <FormRow
                    htmlFor="theme"
                    label="Colour theme"
                    hint="Sets the site's colours and banner backgrounds while this version is active."
                    error={form.errors.theme}
                    className="max-w-sm"
                >
                    <OptionSelect
                        name="theme"
                        value={form.data.theme}
                        onChange={(value) => {
                            form.setData("theme", value);
                            schedule();
                        }}
                        options={options.themes}
                        placeholder="Site default"
                        invalid={!!form.errors.theme}
                    />
                </FormRow>

                {IMAGES.map((image) => (
                    <ImageField key={image.key} image={image} url={gameVersion[`${image.key}_url`]} routes={routes} />
                ))}

                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>
        </Relationships>
    );
}
