import { useState, useRef } from "react";
import { DndContext, closestCenter, PointerSensor, KeyboardSensor, useSensor, useSensors } from "@dnd-kit/core";
import { SortableContext, horizontalListSortingStrategy, useSortable, arrayMove } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";

function SortableImage({ url, onDelete, disabled }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: url });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`group relative inline-block ${disabled ? "cursor-wait" : "cursor-grab"}`}
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
                className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white opacity-0 transition-opacity disabled:cursor-wait group-hover:opacity-100"
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
 * @param {function(string[]):void} props.onReorder - Called with the new ordered URL array
 */
export default function ImageManager({ images, saving, error, onUpload, onDelete, onReorder }) {
    const [isDraggingFile, setIsDraggingFile] = useState(false);
    const fileInputRef = useRef(null);

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
                    <p className="mb-3 text-sm text-gray-400">Drag to reorder &#124; hover to delete</p>
                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                        <SortableContext items={images} strategy={horizontalListSortingStrategy}>
                            <div className="flex flex-wrap gap-4">
                                {images.map((url) => (
                                    <SortableImage key={url} url={url} onDelete={onDelete} disabled={saving} />
                                ))}
                            </div>
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
                            Drag &amp; drop images here, or <span className="text-amber-400 underline">browse</span>
                        </span>
                        <span className="text-xs text-gray-500">JPEG, PNG, WEBP · max 2 MB each</span>
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
