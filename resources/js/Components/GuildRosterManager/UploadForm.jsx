import { useState } from "react";
import { Textarea } from "@headlessui/react";
import { Deferred, useForm } from "@inertiajs/react";
import { FormRow, SaveButton, controlClassName } from "@/Components/FormControls";
import InputError from "@/Components/InputError";
import Kbd from "@/Components/Kbd";
import CopyableCode from "@/Components/CopyableCode";
import ResponsiveText from "@/Components/ResponsiveText";
import Steps, { Step } from "@/Components/Steps";
import ExportSettings from "@/Components/GuildRosterManager/ExportSettings";

/**
 * Replicates the look of a GRM button from the in-game interface, so the
 * instructions visually match what the officer sees on screen.
 */
function InGameButton({ children }) {
    return (
        <span className="font-friz-quadrata border-secondary-600 mx-1 inline-block rounded-md border bg-red-600 px-6 py-2 font-bold text-[#ffff00] shadow-md">
            {children}
        </span>
    );
}

/**
 * The follow-up instruction for guilds larger than GRM's 500-member export
 * limit. `remaining` is null while the member count is still loading.
 */
function ExportNextInstruction({ remaining }) {
    return (
        <p className="leading-loose">
            Over 500 members? GRM exports 500 at a time, so click the
            <InGameButton>Export Next {remaining ?? <span className="italic">X</span>}</InGameButton>
            button and paste that batch under the first.
        </p>
    );
}

/**
 * The paste/drag-and-drop CSV textarea and submit button for GRM data upload.
 * Manages its own form state and posts to the upload endpoint, notifying the
 * parent via `onUploaded` so it can start the progress modal.
 */
export default function UploadForm({ gameVersion, memberCount, onUploaded }) {
    const [isDragging, setIsDragging] = useState(false);

    const { data, setData, post, processing, errors: formErrors } = useForm({ grm_data: "" });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route("management.grm.store", { game_version: gameVersion.slug }), {
            onSuccess: onUploaded,
        });
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const files = e.dataTransfer.files;
        if (files.length === 0) {
            return;
        }

        const file = files[0];
        if (file.type !== "text/csv" && !file.name.endsWith(".csv")) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const content = event.target.result;
            setData("grm_data", data.grm_data ? data.grm_data + "\n" + content : content);
        };
        reader.readAsText(file);
    };

    return (
        <div className="grid gap-10 lg:grid-cols-[minmax(0,26rem)_1fr]">
            <Steps>
                <Step title="Open the export window">
                    <p>
                        Type <CopyableCode>/grm export</CopyableCode> in chat, then pick the <strong>Members</strong>{" "}
                        tab.
                    </p>
                </Step>
                <Step title="Match these settings">
                    <ExportSettings />
                </Step>
                <Step title="Export and paste">
                    <p className="leading-loose">
                        Click
                        <InGameButton>Export Selection</InGameButton>, then select all (<Kbd>Ctrl-A</Kbd>), copy (
                        <Kbd>Ctrl-C</Kbd>) and paste it <ResponsiveText mobile="below" desktop="here" />.
                    </p>
                    <Deferred data="memberCount" fallback={<ExportNextInstruction remaining={null} />}>
                        {memberCount > 500 && <ExportNextInstruction remaining={memberCount - 500} />}
                    </Deferred>
                </Step>
            </Steps>

            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4 lg:sticky lg:top-6 lg:self-start">
                <FormRow
                    htmlFor="grm_data"
                    label="GRM CSV data"
                    required
                    hint="Paste your GRM CSV data, or drag and drop a CSV file."
                    error={formErrors.grm_data}
                >
                    <Textarea
                        id="grm_data"
                        name="grm_data"
                        rows={16}
                        required
                        spellCheck={false}
                        value={data.grm_data}
                        onChange={(e) => setData("grm_data", e.target.value)}
                        onDragOver={handleDragOver}
                        onDragLeave={handleDragLeave}
                        onDrop={handleDrop}
                        invalid={!!formErrors.grm_data}
                        data-dragging={isDragging || undefined}
                        className={`${controlClassName} data-dragging:bg-ground-700 font-mono transition-colors data-dragging:border-blue-500`}
                    />
                </FormRow>
                <InputError message={formErrors.game_version} />

                <SaveButton
                    processing={processing}
                    label={`Upload GRM data for ${gameVersion.title}`}
                    processingLabel="Uploading…"
                />
            </form>
        </div>
    );
}
