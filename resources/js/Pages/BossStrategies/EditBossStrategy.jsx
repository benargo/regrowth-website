import { useState } from "react";
import { useForm, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import MarkdownEditor from "@/Components/MarkdownEditor";
import Icon from "@/Components/FontAwesome/Icon";
import InputLabel from "@/Components/InputLabel";
import InputError from "@/Components/InputError";
import { DndContext, closestCenter, PointerSensor, KeyboardSensor, useSensor, useSensors } from "@dnd-kit/core";
import { SortableContext, horizontalListSortingStrategy, useSortable, arrayMove } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";

function SortableImage({ url, onDelete }) {
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
            className="group relative inline-block cursor-grab"
            {...attributes}
            {...listeners}
        >
            <img src={url} alt="" className="h-32 w-32 rounded border border-amber-600 object-cover" />
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    onDelete(url);
                }}
                className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100"
                title="Delete image"
            >
                <Icon icon="times" style="solid" />
            </button>
        </div>
    );
}

export default function EditBossStrategy({ boss }) {
    // Handle inertia data wrapping
    boss = boss.data ?? boss ?? {};

    const [imageOrder, setImageOrder] = useState(boss.images || []);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor),
    );

    const { data, setData, patch, processing, errors } = useForm({
        notes: boss.notes ?? "",
        images: [],
        deleted_images: [],
        image_order: boss.images || [],
    });

    const visibleImages = imageOrder.filter((url) => !data.deleted_images.includes(url));

    const handleAddImages = (e) => {
        const files = Array.from(e.target.files || []);
        setData("images", [...data.images, ...files]);
    };

    const handleDeleteImage = (url) => {
        setImageOrder((prev) => prev.filter((u) => u !== url));
        setData("deleted_images", [...data.deleted_images, url]);
    };

    const handleDragEnd = (event) => {
        const { active, over } = event;

        if (over && active.id !== over.id) {
            const oldIndex = visibleImages.indexOf(active.id);
            const newIndex = visibleImages.indexOf(over.id);

            if (oldIndex !== -1 && newIndex !== -1) {
                const newOrder = arrayMove(visibleImages, oldIndex, newIndex);
                setImageOrder(newOrder);
                setData("image_order", newOrder);
            }
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route("dashboard.boss-strategies.update", { boss: boss.id }), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <Master title={`Edit Strategy — ${boss.name}`}>
            <SharedHeader title={boss.name} backgroundClass={boss.raid.background ?? "bg-officer-meeting"} />
            {/* Tool navigation */}
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto max-w-4xl px-4">
                    <div className="flex min-h-12 flex-col items-center justify-between md:flex-row">
                        <div className="flex-initial space-x-4">
                            <Link
                                href={route("dashboard.boss-strategies.index")}
                                className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                            >
                                <Icon icon="arrow-left" style="solid" className="mr-2" />
                                <span>Back to {boss.raid.name} strategies</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
            <div className="py-12 text-white">
                <div className="container mx-auto max-w-4xl px-4">
                    <form onSubmit={handleSubmit} className="space-y-8">
                        {/* Notes Section */}
                        <div>
                            <h3 className="not-sr-only mb-4 text-lg font-bold">Strategy Notes</h3>
                            <InputLabel htmlFor="notes" value="Strategy Notes" className="sr-only text-gray-300" />
                            <MarkdownEditor
                                value={data.notes}
                                onChange={(value) => setData("notes", value)}
                                rows={12}
                                error={errors.notes}
                                className="mt-2"
                            />
                        </div>

                        {/* Image Management Section */}
                        <div>
                            <h3 className="mb-4 text-lg font-bold">Strategy Images</h3>

                            {/* Current Images */}
                            {visibleImages.length > 0 && (
                                <div className="mb-6">
                                    <p className="mb-3 text-sm text-gray-400">Drag to reorder, click the × to delete</p>
                                    <DndContext
                                        sensors={sensors}
                                        collisionDetection={closestCenter}
                                        onDragEnd={handleDragEnd}
                                    >
                                        <SortableContext items={visibleImages} strategy={horizontalListSortingStrategy}>
                                            <div className="flex flex-wrap gap-4">
                                                {visibleImages.map((url) => (
                                                    <SortableImage key={url} url={url} onDelete={handleDeleteImage} />
                                                ))}
                                            </div>
                                        </SortableContext>
                                    </DndContext>
                                </div>
                            )}

                            {/* Upload New Images */}
                            <div className="mb-6">
                                <InputLabel htmlFor="images" value="Upload New Images" className="text-gray-300" />
                                <input
                                    id="images"
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={handleAddImages}
                                    className="mt-2 block w-full text-sm text-gray-400 file:mr-4 file:rounded file:border-0 file:bg-amber-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-amber-700"
                                />
                                <InputError message={errors.images} className="mt-2" />
                                {data.images.length > 0 && (
                                    <div className="mt-3 space-y-2">
                                        {data.images.map((file, idx) => (
                                            <div
                                                key={idx}
                                                className="flex items-center justify-between rounded bg-amber-600/10 p-2 text-sm"
                                            >
                                                <span>{file.name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setData(
                                                            "images",
                                                            data.images.filter((_, i) => i !== idx),
                                                        )
                                                    }
                                                    className="text-red-400 hover:text-red-300"
                                                >
                                                    <Icon icon="times" style="solid" className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Form Actions */}
                        <div className="flex justify-between gap-4">
                            <Link
                                href={route("dashboard.boss-strategies.index")}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-gray-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition duration-150 ease-in-out hover:bg-brown-600"
                            >
                                Back
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-amber-700 ${
                                    processing ? "opacity-25" : ""
                                }`}
                            >
                                {processing ? "Saving..." : "Save Strategy"}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Master>
    );
}
