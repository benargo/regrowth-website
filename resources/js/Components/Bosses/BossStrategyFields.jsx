import { useEffect, useMemo, useState } from "react";
import { router, useForm, usePage } from "@inertiajs/react";
import Relationships from "@/Components/Datasets/Relationships";
import { firstError } from "@/Components/FormControls";
import ImageManager from "@/Components/ImageManager";
import MarkdownEditor from "@/Components/MarkdownEditor";
import { autosaveVisit, useAutosaveQueue, useAutosaveStatus, useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

const AUTOSAVE_HEADERS = { "X-Autosave": "1" };

/**
 * The boss's strategy on its Edit page: markdown notes that save shortly
 * after typing stops, and images that save as soon as they are uploaded,
 * removed or reordered. Images are sent by media id. Without `canEdit`, the
 * images can't be uploaded, removed or reordered.
 */
export default function BossStrategyFields({ boss, url, canEdit = true }) {
    const savedNotes = useMemo(() => ({ notes: boss.notes ?? "" }), [boss.notes]);
    const notesForm = useForm(savedNotes);
    const { schedule } = useFormAutosave({
        form: notesForm,
        url,
        saved: savedNotes,
        key: "strategy-notes",
        delay: AUTOSAVE_DELAY,
    });

    const queue = useAutosaveQueue();
    const status = useAutosaveStatus();
    const { errors } = usePage().props;
    const [images, setImages] = useState(boss.images);

    useEffect(() => setImages(boss.images), [boss.images]);

    const imageError =
        firstError(errors, "images") ?? firstError(errors, "removed_image_ids") ?? firstError(errors, "image_ids");

    function saveImages(key, data, options = {}) {
        if (!canEdit) {
            return;
        }

        const run = () => autosaveVisit(router, "patch", url, { data, headers: AUTOSAVE_HEADERS, ...options });

        if (queue) {
            queue.enqueue(key, run);
        } else {
            run();
        }
    }

    function handleUpload(files) {
        saveImages(`strategy-images-upload-${Date.now()}`, { images: files }, { forceFormData: true });
    }

    /**
     * Put the saved images back after a removal or reorder the server refused.
     */
    function rollBackImages() {
        setImages(boss.images);
    }

    function handleDelete(imageUrl) {
        const image = images.find((candidate) => candidate.url === imageUrl);

        if (!canEdit || !image) {
            return;
        }

        setImages((current) => current.filter((candidate) => candidate.id !== image.id));
        saveImages(
            `strategy-images-remove-${image.id}`,
            { removed_image_ids: [image.id] },
            { onError: rollBackImages, onNetworkError: rollBackImages },
        );
    }

    function handleReorder(imageUrls) {
        const reordered = imageUrls.map((imageUrl) => images.find((image) => image.url === imageUrl));

        if (!canEdit || reordered.includes(undefined)) {
            return;
        }

        setImages(reordered);
        saveImages(
            "strategy-images-order",
            { image_ids: reordered.map((image) => image.id) },
            { onError: rollBackImages, onNetworkError: rollBackImages },
        );
    }

    return (
        <Relationships
            id="strategy"
            title="Strategy"
            description="Members see these notes and images on the boss's strategy page and in raid plans."
        >
            <div className="flex flex-col gap-1.5">
                <label htmlFor="notes" className="text-sm font-medium text-gray-300">
                    Strategy notes
                </label>
                <MarkdownEditor
                    id="notes"
                    value={notesForm.data.notes}
                    onChange={(value) => {
                        notesForm.setData("notes", value);
                        schedule();
                    }}
                    rows={12}
                    error={notesForm.errors.notes}
                />
            </div>

            <div className="flex flex-col gap-1.5">
                <h3 className="text-sm font-medium text-gray-300">Strategy images</h3>
                <ImageManager
                    images={images.map((image) => image.url)}
                    saving={status?.status === "saving"}
                    disabled={!canEdit}
                    error={imageError}
                    onUpload={handleUpload}
                    onDelete={handleDelete}
                    onReorder={handleReorder}
                />
            </div>
        </Relationships>
    );
}
