import { useId, useState, useRef } from "react";
import { DndContext, closestCenter, PointerSensor, KeyboardSensor, useSensor, useSensors } from "@dnd-kit/core";
import { SortableContext, horizontalListSortingStrategy, useSortable, arrayMove } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import Icon from "@/Components/FontAwesome/Icon";
import { FieldError } from "@/Components/FormControls";

const DEFAULT_ACCEPTS = ["image/jpeg", "image/png", "image/webp"];

/**
 * Turn MIME types into a readable list, e.g. "JPEG, PNG, WEBP, SVG".
 */
function describeTypes(mimeTypes) {
    return mimeTypes.map((type) => type.split("/").pop().split("+")[0].toUpperCase()).join(", ");
}

function SortableImage({ url, onDelete, disabled, sortable }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: url,
        disabled: !sortable,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`group relative inline-block ${disabled ? "cursor-wait" : sortable ? "cursor-grab" : ""}`}
            {...attributes}
            {...listeners}
        >
            <img src={url} alt="" className="h-32 w-32 rounded border border-amber-600 object-cover" />
            <button
                type="button"
                disabled={disabled}
                onClick={(e) => {
                    e.stopPropagation();
                    onDelete(url);
                }}
                className="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 disabled:cursor-wait"
                title="Delete image"
            >
                <Icon icon="times" style="solid" />
            </button>
        </div>
    );
}

/**
 * @param {object} props
 * @param {string[]} props.images          - Ordered list of image URLs
 * @param {boolean} props.saving           - Whether an upload/reorder/delete is in flight
 * @param {string}  [props.error]          - Validation error message
 * @param {function(File[]):void} props.onUpload   - Called with new File objects to upload
 * @param {function(string):void} props.onDelete   - Called with the URL to delete
 * @param {function(string[]):void} [props.onReorder] - Called with the new ordered URL array; not needed when `max` is 1
 * @param {number|null} [props.max=null]   - Most images allowed; the upload box hides once it is reached, and a
 *                                           max of 1 makes it a single-image field
 * @param {string[]} [props.accepts]       - MIME types allowed; defaults to JPEG, PNG and WEBP
 */
export default function ImageManager({
    images,
    saving,
    error,
    onUpload,
    onDelete,
    onReorder,
    max = null,
    accepts = DEFAULT_ACCEPTS,
}) {
    const [isDraggingFile, setIsDraggingFile] = useState(false);
    const remaining = max === null ? Infinity : max - images.length;
    const multiple = max !== 1;
    const fileInputRef = useRef(null);
    const hintId = useId();

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor),
    );

    const handleDragEnd = (event) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const oldIndex = images.indexOf(active.id);
        const newIndex = images.indexOf(over.id);
        if (oldIndex === -1 || newIndex === -1) return;

        onReorder?.(arrayMove(images, oldIndex, newIndex));
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
        const files = Array.from(e.dataTransfer.files).filter((f) => accepts.includes(f.type));
        if (files.length) {
            onUpload(files.slice(0, remaining));
        }
    };

    const handleFileInputChange = (e) => {
        const files = Array.from(e.target.files || []).slice(0, remaining);
        if (files.length) {
            onUpload(files);
        }
        e.target.value = "";
    };

    return (
        <div>
            {images.length > 0 && (
                <div className="mb-6">
                    <p className="mb-3 text-sm text-gray-400">
                        {multiple ? <>Drag to reorder &#124; hover to delete</> : "Hover to delete"}
                    </p>
                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                        <SortableContext items={images} strategy={horizontalListSortingStrategy}>
                            <div className="flex flex-wrap gap-4">
                                {images.map((url) => (
                                    <SortableImage
                                        key={url}
                                        url={url}
                                        onDelete={onDelete}
                                        disabled={saving}
                                        sortable={multiple}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </DndContext>
                </div>
            )}

            {remaining > 0 && (
                <div
                    onDragOver={handleFileDragOver}
                    onDragEnter={handleFileDragOver}
                    onDragLeave={handleFileDragLeave}
                    onDrop={handleFileDrop}
                    onClick={() => fileInputRef.current?.click()}
                    className={`flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors ${
                        isDraggingFile
                            ? "border-amber-400 bg-amber-600/10 text-amber-300"
                            : "border-gray-600 text-gray-400 hover:border-amber-600 hover:text-gray-300"
                    }`}
                >
                    {saving ? (
                        <>
                            <Icon icon="spinner" style="solid" className="fa-spin text-2xl text-amber-400" />
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
                                Drag &amp; drop {multiple ? "images" : "an image"} here, or{" "}
                                <button
                                    type="button"
                                    aria-describedby={hintId}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        fileInputRef.current?.click();
                                    }}
                                    className="rounded-sm text-amber-400 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                                >
                                    browse
                                </button>
                            </span>
                            <span id={hintId} className="text-xs text-gray-400">
                                {describeTypes(accepts)} · max 2 MB{multiple ? " each" : ""}
                            </span>
                        </>
                    )}
                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple={multiple}
                        accept={accepts.join(",")}
                        onChange={handleFileInputChange}
                        aria-label={multiple ? "Upload images" : "Upload image"}
                        className="hidden"
                    />
                </div>
            )}

            <FieldError message={error} className="mt-2" />
        </div>
    );
}
