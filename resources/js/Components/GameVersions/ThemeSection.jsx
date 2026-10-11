import { memo, useState } from "react";
import { router, useForm } from "@inertiajs/react";
import ColorPicker from "@/Components/ColorPicker";
import Relationships from "@/Components/Datasets/Relationships";
import { FormRow, SaveButton } from "@/Components/FormControls";
import ImageManager from "@/Components/ImageManager";
import { toHex } from "@/Helpers/ModelColor";
import { autosaveVisit, useAutosaveQueue, useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/** A stable fallback, so a missing `media` prop doesn't defeat ImageField's memo. */
const NO_MEDIA = {};

/**
 * One image upload. Files are sent as soon as they are chosen, with the PATCH
 * spoofed over POST because browsers can't send files in a real PATCH.
 *
 * On an autosaving page, each upload or delete waits its turn in the save
 * queue, so its visit neither cancels nor is cancelled by an autosave.
 *
 * Memoised so dragging the colour picker doesn't re-render every uploader.
 */
const ImageField = memo(function ImageField({ field, label, hint, gameVersion, media, routes }) {
    const url = gameVersion[`${field}_url`];
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const queue = useAutosaveQueue();

    function send(method, data, options = {}) {
        const run = () =>
            autosaveVisit(router, method, routes.update, {
                data,
                ...options,
                onStart: () => setSaving(true),
                onSuccess: () => setError(null),
                onError: (errors) => setError(errors[field] ?? null),
                onFinish: () => setSaving(false),
            });

        if (queue) {
            queue.enqueue(`image-${field}`, run);
            return;
        }

        run();
    }

    return (
        <FormRow label={label} hint={hint}>
            <ImageManager
                images={url ? [url] : []}
                saving={saving}
                error={error}
                max={1}
                accepts={media[field]?.accepts}
                onUpload={([file]) => send("post", { _method: "patch", [field]: file }, { forceFormData: true })}
                onDelete={() => send("patch", { [field]: null })}
            />
        </FormRow>
    );
});

export default function ThemeSection({
    gameVersion,
    media = NO_MEDIA,
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
                    gameVersion={gameVersion}
                    media={media}
                    routes={routes}
                />

                {/* The logo and icon are square, so they sit side by side on wider screens. */}
                <div className="grid gap-8 md:grid-cols-2">
                    <ImageField
                        field="logo"
                        label="Logo"
                        hint="The game's full-colour logo."
                        gameVersion={gameVersion}
                        media={media}
                        routes={routes}
                    />
                    <ImageField
                        field="icon"
                        label="Icon"
                        hint="A single-colour version of the logo, for small spaces such as menus."
                        gameVersion={gameVersion}
                        media={media}
                        routes={routes}
                    />
                </div>

                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>
        </Relationships>
    );
}
