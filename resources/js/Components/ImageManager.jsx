import { useState, useRef } from "react";
import { DndContext, closestCenter, PointerSensor, KeyboardSensor, useSensor, useSensors } from "@dnd-kit/core";
import {
    SortableContext,
    horizontalListSortingStrategy,
    useSortable,
    arrayMove,
    sortableKeyboardCoordinates,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";

function SortableImage({ url, position, total, onDelete, disabled }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: url });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <li ref={setNodeRef} style={style} className="group relative">
            <div
                {...attributes}
                {...listeners}
                aria-label={`Image ${position} of ${total}`}
                className={`rounded focus:outline-hidden focus-visible:ring-2 focus-visible:ring-primary ${disabled ? "cursor-wait" : "cursor-grab"}`}
            >
                <img src={url} alt="" className="h-32 w-32 rounded border border-ink-600 object-cover" />
            </div>
            <button
                type="button"
                disabled={disabled}
                onClick={() => onDelete(url)}
                aria-label={`Delete image ${position} of ${total}`}
                title={`Delete image ${position}`}
                className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 focus:outline-hidden focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-white disabled:cursor-wait pointer-coarse:opacity-100"
            >
                <Icon icon="times" style="solid" />
            </button>
        </li>
    );
}

/**
 * @param {object} props
 * @param {string[]} props.images          - Ordered list of image URLs
 * @param {boolean} props.saving           - Whether an upload/reorder/delete is in flight
 * @param {string}  [props.error]          - Validation error message
 * @param {function(File[]):void} props.onUpload   - Called with new File objects to upload
 * @param {function(string):void} props.onDelete   - Called with the URL to delete
 * @param {function(string[]):void} props.onReorder - Called with the new ordered URL array
 */
export default function ImageManager({ images, saving, error, onUpload, onDelete, onReorder }) {
    const [isDraggingFile, setIsDraggingFile] = useState(false);
    const fileInputRef = useRef(null);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const positionOf = (id) => images.indexOf(id) + 1;

    const announcements = {
        onDragStart: ({ active }) => `Picked up image ${positionOf(active.id)} of ${images.length}.`,
        onDragOver: ({ active, over }) =>
            over
                ? `Image ${positionOf(active.id)} is over position ${positionOf(over.id)} of ${images.length}.`
                : `Image ${positionOf(active.id)} is no longer over a position.`,
        onDragEnd: ({ active, over }) =>
            over
                ? `Image ${positionOf(active.id)} was moved to position ${positionOf(over.id)} of ${images.length}.`
                : `Image ${positionOf(active.id)} was dropped.`,
        onDragCancel: ({ active }) =>
            `Reordering cancelled. Image ${positionOf(active.id)} was returned to its position.`,
    };

    const handleDragEnd = (event) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const oldIndex = images.indexOf(active.id);
        const newIndex = images.indexOf(over.id);
        if (oldIndex === -1 || newIndex === -1) return;

        onReorder(arrayMove(images, oldIndex, newIndex));
    };

    const handleFileDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer.types.includes("Files")) {
            setIsDraggingFile(true);
        }
    };

    const handleFileDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!e.currentTarget.contains(e.relatedTarget)) {
            setIsDraggingFile(false);
        }
    };

    const handleFileDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDraggingFile(false);
        const files = Array.from(e.dataTransfer.files).filter((f) =>
            ["image/jpeg", "image/png", "image/webp"].includes(f.type),
        );
        if (files.length) {
            onUpload(files);
        }
    };

    const handleFileInputChange = (e) => {
        const files = Array.from(e.target.files || []);
        if (files.length) {
            onUpload(files);
        }
        e.target.value = "";
    };

    return (
        <div>
            {images.length > 0 && (
                <div className="mb-6">
                    <p className="mb-3 text-sm text-secondary-400">
                        Drag to reorder, or focus an image and press Space, then the arrow keys, then Space again to
                        drop it. Hover or focus an image to delete it.
                    </p>
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={handleDragEnd}
                        accessibility={{ announcements }}
                    >
                        <SortableContext items={images} strategy={horizontalListSortingStrategy}>
                            <ul aria-label="Uploaded images" className="flex flex-wrap gap-4">
                                {images.map((url, index) => (
                                    <SortableImage
                                        key={url}
                                        url={url}
                                        position={index + 1}
                                        total={images.length}
                                        onDelete={onDelete}
                                        disabled={saving}
                                    />
                                ))}
                            </ul>
                        </SortableContext>
                    </DndContext>
                </div>
            )}

            <div
                onDragOver={handleFileDragOver}
                onDragEnter={handleFileDragOver}
                onDragLeave={handleFileDragLeave}
                onDrop={handleFileDrop}
                onClick={() => fileInputRef.current?.click()}
                className={`flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors ${
                    isDraggingFile
                        ? "border-ink-400 bg-ink-600/10 text-ink-300"
                        : "border-secondary-600 text-secondary-400 hover:border-ink-600 hover:text-secondary-300"
                }`}
            >
                {saving ? (
                    <>
                        <Icon icon="spinner" style="solid" className="fa-spin text-2xl text-ink-400" />
                        <span className="text-sm">Uploading…</span>
                    </>
                ) : isDraggingFile ? (
                    <>
                        <Icon icon="image" style="solid" className="text-2xl" />
                        <span className="text-sm font-medium">Drop to upload</span>
                    </>
                ) : (
                    <>
                        <Icon icon="cloud-upload" style="solid" className="text-2xl" />
                        <span className="text-sm">
                            Drag &amp; drop images here, or <span className="text-ink-400 underline">browse</span>
                        </span>
                        <span className="text-xs text-secondary-500">JPEG, PNG, WEBP · max 2 MB each</span>
                    </>
                )}
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    onChange={handleFileInputChange}
                    className="hidden"
                />
            </div>

            <InputError message={error} className="mt-2" />
        </div>
    );
}
