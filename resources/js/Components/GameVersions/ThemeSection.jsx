import { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import ColorPicker from "@/Components/ColorPicker";
import Relationships from "@/Components/Datasets/Relationships";
import { FormRow, SaveButton } from "@/Components/FormControls";
import ImageManager from "@/Components/ImageManager";
import { toHex } from "@/Helpers/ModelColor";
import { useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/**
 * One image upload. Files are sent as soon as they are chosen, with the PATCH
 * spoofed over POST because browsers can't send files in a real PATCH.
 */
function ImageField({ field, label, hint, url, max, accepts, routes }) {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    function send(method, data, options = {}) {
        router[method](routes.update, data, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onSuccess: () => setError(null),
            onError: (errors) => setError(errors[field] ?? null),
            onFinish: () => setSaving(false),
            ...options,
        });
    }

    return (
        <FormRow label={label} hint={hint}>
            <ImageManager
                images={url ? [url] : []}
                saving={saving}
                error={error}
                multiple={false}
                max={max}
                accepts={accepts}
                onUpload={([file]) => send("post", { _method: "patch", [field]: file }, { forceFormData: true })}
                onDelete={() => send("patch", { [field]: null })}
            />
        </FormRow>
    );
}

export default function ThemeSection({
    gameVersion,
    media = {},
    routes,
    submitLabel = "Save theme",
    onSaved,
    autosave = false,
}) {
    const saved = { color: toHex(gameVersion.color) };
    const form = useForm(saved);
    const { schedule, containerProps } = useFormAutosave({
        form,
        url: routes.update,
        saved,
        key: "color",
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
            description="Choose this version's colour and upload its images. Images save as soon as you add or delete them."
        >
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8" {...containerProps}>
                <FormRow
                    label="Colour"
                    hint="Fills this version's page headers, behind the banner image."
                    error={form.errors.color}
                >
                    {/* The picker is controlled, so onChange keeps it in step while dragging. */}
                    <ColorPicker
                        color={form.data.color ?? "#000000"}
                        onChange={(color) => form.setData("color", color.hex)}
                        onChangeComplete={() => schedule()}
                    />
                </FormRow>

                <ImageField
                    field="banner"
                    label="Banner"
                    hint="The wide header image shown at the top of this version's pages."
                    url={gameVersion.banner_url}
                    max={media.banner?.limit ?? null}
                    accepts={media.banner?.accepts}
                    routes={routes}
                />

                {/* The logo and icon are square, so they sit side by side on wider screens. */}
                <div className="grid gap-8 md:grid-cols-2">
                    <ImageField
                        field="logo"
                        label="Logo"
                        hint="The game's full-colour logo."
                        url={gameVersion.logo_url}
                        max={media.logo?.limit ?? null}
                        accepts={media.logo?.accepts}
                        routes={routes}
                    />
                    <ImageField
                        field="icon"
                        label="Icon"
                        hint="A single-colour version of the logo, for small spaces such as menus."
                        url={gameVersion.icon_url}
                        max={media.icon?.limit ?? null}
                        accepts={media.icon?.accepts}
                        routes={routes}
                    />
                </div>

                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>
        </Relationships>
    );
}
