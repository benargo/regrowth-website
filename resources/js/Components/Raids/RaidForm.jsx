import ColorPicker from "@/Components/ColorPicker";
import DetailsForm from "@/Components/Datasets/DetailsForm";
import ImageUploadField from "@/Components/Datasets/ImageUploadField";
import { FormRow, FormSection, linkClassName } from "@/Components/FormControls";
import { modelBackground, toHex } from "@/Helpers/ModelColor";

const RENAME_HINT =
    "Raid-Helper event descriptions must use this exact name, or their zones are skipped when events sync.";

/**
 * Build the initial useForm state for a raid, or for a new raid in the given
 * phase. The banner position is part of the state only when editing.
 */
export function raidFormData(raid = null, phaseId = null) {
    return {
        name: raid?.name ?? "",
        difficulty: raid?.difficulty ?? "",
        phase_id: raid ? String(raid.phase_id) : (phaseId?.toString() ?? ""),
        color: toHex(raid?.color) ?? "",
        max_players: raid?.max_players?.toString() ?? "",
        max_loot_councillors: raid?.max_loot_councillors?.toString() ?? "",
        ...(raid ? { banner_position: raid.banner_position ?? "" } : {}),
    };
}

/**
 * The banner as page headers show it, at the chosen position.
 */
function BannerPreview({ color, url, position }) {
    const background = modelBackground(color, url, position || null);

    return (
        <div
            aria-hidden="true"
            className={`${background.className} aspect-14/3 w-full rounded border border-amber-600/40 bg-cover bg-center`}
            style={background.style}
        />
    );
}

/**
 * The raid details form, for creating or editing. With a stored `raid`, it
 * also manages the banner (uploads save straight to `updateUrl`) and its
 * position. Other props pass through to DetailsForm.
 */
export default function RaidForm({ form, options, raid = null, media = null, updateUrl = null, ...detailsFormProps }) {
    const { data, errors, setData } = form;
    const isColorUnset = data.color === "";

    return (
        <DetailsForm form={form} {...detailsFormProps}>
            {({ text, select, clear, schedule }) => (
                <>
                    <FormSection legend="The raid">
                        <FormRow htmlFor="name" label="Name" required hint={RENAME_HINT} error={errors.name}>
                            {text("name", { required: true, maxLength: 255, autoComplete: "off" })}
                        </FormRow>
                        <FormRow
                            htmlFor="difficulty"
                            label="Difficulty"
                            required
                            hint="For example Normal, Heroic or Mythic."
                            error={errors.difficulty}
                        >
                            {text("difficulty", { required: true, maxLength: 50, autoComplete: "off" })}
                        </FormRow>
                        <FormRow
                            htmlFor="phase_id"
                            label="Phase"
                            required
                            hint="Once the raid has bosses, events or items, it can only move to a phase in the same game version."
                            error={errors.phase_id}
                            className="md:col-span-2"
                        >
                            {select("phase_id", { groups: options.phases, placeholder: "Choose a phase", required: true })}
                        </FormRow>
                    </FormSection>

                    <FormSection legend="Raid size">
                        <FormRow htmlFor="max_players" label="Maximum players" error={errors.max_players}>
                            {text("max_players", { type: "number", min: "1", max: "40", inputMode: "numeric" })}
                        </FormRow>
                        <FormRow
                            htmlFor="max_loot_councillors"
                            label="Maximum loot councillors"
                            hint="No more than the maximum players."
                            error={errors.max_loot_councillors}
                        >
                            {text("max_loot_councillors", { type: "number", min: "1", inputMode: "numeric" })}
                        </FormRow>
                    </FormSection>

                    <FormSection legend="Appearance">
                        <FormRow
                            label="Colour"
                            hint={`${isColorUnset ? "Not set. " : ""}Fills the raid's page headers, behind the banner.`}
                            error={errors.color}
                        >
                            <ColorPicker
                                label={isColorUnset ? "Colour, not set" : "Colour"}
                                color={data.color || "#000000"}
                                onChange={(color) => setData("color", color.hex)}
                                onChangeComplete={() => schedule()}
                            />
                        </FormRow>
                        {data.color !== "" && (
                            <div className="flex items-end">
                                <button type="button" onClick={() => clear("color")} className={linkClassName}>
                                    Clear colour
                                </button>
                            </div>
                        )}

                        {raid && (
                            <>
                                <div className="md:col-span-2">
                                    <ImageUploadField
                                        field="banner"
                                        label="Banner"
                                        hint="The wide image at the top of the raid's pages. It saves as soon as you add or delete it."
                                        url={raid.banner_url}
                                        accepts={media?.banner?.accepts}
                                        updateUrl={updateUrl}
                                    />
                                </div>
                                <FormRow
                                    htmlFor="banner_position"
                                    label="Banner position"
                                    hint={
                                        raid.banner_url
                                            ? "Which part of the banner stays in view when it's cropped."
                                            : "Upload a banner first."
                                    }
                                    error={errors.banner_position}
                                >
                                    {select("banner_position", {
                                        options: options.banner_positions,
                                        placeholder: "Not set (middle)",
                                        disabled: !raid.banner_url,
                                    })}
                                </FormRow>
                                {raid.banner_url && (
                                    <div className="flex flex-col gap-1.5">
                                        <span className="text-sm font-medium text-gray-300">Preview</span>
                                        <BannerPreview
                                            color={data.color}
                                            url={raid.banner_url}
                                            position={data.banner_position}
                                        />
                                    </div>
                                )}
                            </>
                        )}
                    </FormSection>
                </>
            )}
        </DetailsForm>
    );
}
