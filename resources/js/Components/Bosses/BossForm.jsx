import { Link } from "@inertiajs/react";
import DetailsForm from "@/Components/Datasets/DetailsForm";
import { FormRow, FormSection, linkClassName } from "@/Components/FormControls";

/**
 * Build the initial useForm state: the name for an existing boss, or the
 * name, raid and form origin for a new one.
 */
export function bossFormData(boss = null, raidId = null, from = null) {
    if (boss) {
        return { name: boss.name ?? "" };
    }

    return { name: "", raid_id: raidId?.toString() ?? "", from: from ?? "" };
}

/**
 * The boss details form. With `raid` ({ name, href }) the raid is shown
 * read-only with a link; otherwise the raid is chosen from `options.raids`,
 * grouped by game version. Other props pass through to DetailsForm.
 */
export default function BossForm({ form, options = null, raid = null, ...detailsFormProps }) {
    const { errors } = form;

    return (
        <DetailsForm form={form} {...detailsFormProps}>
            {({ text, select }) => (
                <FormSection legend="The boss">
                    <FormRow htmlFor="name" label="Name" required error={errors.name}>
                        {text("name", { required: true, maxLength: 255, autoComplete: "off" })}
                    </FormRow>

                    {raid ? (
                        <div className="flex flex-col gap-1.5">
                            <p className="text-sm font-medium text-gray-300">Raid</p>
                            <p className="text-white">
                                <Link href={raid.href} className={linkClassName}>
                                    {raid.name}
                                </Link>
                            </p>
                            <p className="text-sm text-gray-300">
                                A boss can't move to another raid. To move it, delete it and add it to the other raid.
                            </p>
                        </div>
                    ) : (
                        <FormRow
                            htmlFor="raid_id"
                            label="Raid"
                            required
                            hint="New bosses go to the end of the raid's encounter order."
                            error={errors.raid_id}
                        >
                            {select("raid_id", {
                                groups: options.raids,
                                placeholder: "Choose a raid",
                                required: true,
                            })}
                        </FormRow>
                    )}
                </FormSection>
            )}
        </DetailsForm>
    );
}
