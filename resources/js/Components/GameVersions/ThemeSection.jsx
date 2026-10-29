import { useForm } from "@inertiajs/react";
import ColorPicker from "@/Components/ColorPicker";
import ImageUploadField from "@/Components/Datasets/ImageUploadField";
import Relationships from "@/Components/Datasets/Relationships";
import { FormRow, SaveButton } from "@/Components/FormControls";
import { toHex } from "@/Helpers/ModelColor";
import { useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/** A stable fallback for a missing `media` prop. */
const NO_MEDIA = {};

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
                        label="Colour"
                        color={form.data.color ?? "#000000"}
                        onChange={(color) => form.setData("color", color.hex)}
                        onChangeComplete={() => schedule()}
                    />
                </FormRow>

                <ImageUploadField
                    field="banner"
                    label="Banner"
                    hint="The wide header image shown at the top of this version's pages."
                    url={gameVersion.banner_url}
                    accepts={media.banner?.accepts}
                    updateUrl={routes.update}
                />

                {/* The logo and icon are square, so they sit side by side on wider screens. */}
                <div className="grid gap-8 md:grid-cols-2">
                    <ImageUploadField
                        field="logo"
                        label="Logo"
                        hint="The game's full-colour logo."
                        url={gameVersion.logo_url}
                        accepts={media.logo?.accepts}
                        updateUrl={routes.update}
                    />
                    <ImageUploadField
                        field="icon"
                        label="Icon"
                        hint="A single-colour version of the logo, for small spaces such as menus."
                        url={gameVersion.icon_url}
                        accepts={media.icon?.accepts}
                        updateUrl={routes.update}
                    />
                </div>

                {!autosave && <SaveButton processing={form.processing} label={submitLabel} />}
            </form>
        </Relationships>
    );
}
