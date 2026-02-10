import { useState, useMemo, useEffect, useRef } from "react";
import { Link, useForm } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import CommentsSection from "@/Components/Loot/CommentsSection";
import Icon from "@/Components/FontAwesome/Icon";
import ItemDetailsCard from "@/Components/Loot/ItemDetailsCard";
import Notes from "@/Components/Loot/Notes";
import SharedHeader from "@/Components/SharedHeader";
import {
    DndContext,
    DragOverlay,
    closestCenter,
    PointerSensor,
    KeyboardSensor,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { useDroppable } from "@dnd-kit/core";

function DraggablePriorityItem({ priority, onRemove }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: priority.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="min-w-50 relative flex cursor-grab items-center justify-center gap-2 rounded-md border border-primary bg-brown-800 p-6"
            {...attributes}
            {...listeners}
        >
            {priority.media && <img src={priority.media} alt="" className="h-6 w-6 rounded-sm" />}
            <span>{priority.title}</span>
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    onRemove(priority.id);
                }}
                className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white transition-colors hover:bg-red-700"
            >
                <Icon icon="times" style="solid" />
            </button>
        </div>
    );
}

function PriorityOverlay({ priority }) {
    if (!priority) return null;

    return (
        <div className="flex w-60 cursor-grabbing items-center justify-center gap-2 rounded-md border border-primary bg-brown-800 p-6 shadow-lg">
            {priority.media && <img src={priority.media} alt="" className="h-6 w-6 rounded-sm" />}
            <span>{priority.title}</span>
        </div>
    );
}

function DroppableWeightRow({ weight, children, onAddClick }) {
    const { setNodeRef, isOver } = useDroppable({
        id: `weight-${weight}`,
        data: { weight, type: "weight-row" },
    });

    return (
        <div
            ref={setNodeRef}
            className={`flex items-center justify-center transition-colors ${isOver ? "bg-amber-900/30" : ""}`}
        >
            <div className="w-12 flex-none text-4xl">{weight + 1}</div>
            <div className="ml-4 flex w-full flex-wrap items-center justify-center gap-4 py-4">
                {children}
                <button
                    type="button"
                    onClick={() => onAddClick(weight)}
                    className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-600 text-white transition-colors hover:bg-amber-700"
                >
                    <Icon icon="plus" style="solid" />
                </button>
            </div>
        </div>
    );
}

function InsertWeightZone({ afterWeight, onDrop, onAddClick }) {
    const [isHovered, setIsHovered] = useState(false);
    const { setNodeRef, isOver } = useDroppable({
        id: `insert-${afterWeight}`,
        data: { afterWeight, type: "insert-zone" },
    });

    return (
        <div
            ref={setNodeRef}
            className="relative my-2"
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <div className="my-4 text-center text-4xl font-bold text-amber-600">
                <Icon icon="chevron-down" style="solid" />
            </div>
            <div
                className={`absolute inset-x-0 top-1/2 flex -translate-y-1/2 items-center justify-center transition-opacity ${
                    isHovered || isOver ? "opacity-100" : "opacity-0"
                }`}
            >
                <button
                    type="button"
                    onClick={() => onAddClick(afterWeight)}
                    className={`flex h-10 w-10 items-center justify-center rounded-full transition-colors ${
                        isOver ? "bg-amber-500 text-white" : "bg-amber-600 text-white hover:bg-amber-700"
                    }`}
                >
                    <Icon icon="plus" style="solid" />
                </button>
            </div>
        </div>
    );
}

function AddNewWeightRow({ weight, onAddClick }) {
    const { setNodeRef, isOver } = useDroppable({
        id: `new-weight-${weight}`,
        data: { weight, type: "new-weight" },
    });

    return (
        <div
            ref={setNodeRef}
            className={`flex items-center justify-center rounded-lg border-2 border-dashed py-8 transition-colors ${
                isOver ? "border-amber-500 bg-amber-900/20" : "border-amber-600/30"
            }`}
        >
            <button
                type="button"
                onClick={() => onAddClick(weight)}
                className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-600 text-white transition-colors hover:bg-amber-700"
            >
                <Icon icon="plus" style="solid" />
            </button>
            <span className="ml-4 text-gray-400">Add new priority level</span>
        </div>
    );
}

function PriorityPickerModal({ isOpen, onClose, priorities, onSelect }) {
    if (!isOpen) return null;

    const groupedPriorities = useMemo(() => {
        return priorities.reduce((acc, priority) => {
            const type = priority.type || "other";
            if (!acc[type]) {
                acc[type] = [];
            }
            acc[type].push(priority);
            return acc;
        }, {});
    }, [priorities]);

    const typeLabels = {
        role: "Roles",
        class: "Classes",
        spec: "Specializations",
        other: "Other",
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div
                className="max-h-[80vh] max-w-2xl overflow-y-auto rounded-lg border border-primary bg-brown-900 p-6"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-xl font-bold">Select Priority</h3>
                    <button type="button" onClick={onClose} className="text-gray-400 hover:text-white">
                        <Icon icon="times" style="solid" />
                    </button>
                </div>
                {Object.entries(groupedPriorities).map(([type, typePriorities]) => (
                    <div key={type} className="mb-4">
                        <h4 className="mb-2 text-sm font-semibold uppercase text-amber-500">
                            {typeLabels[type] || type}
                        </h4>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {typePriorities.map((priority) => (
                                <button
                                    key={priority.id}
                                    type="button"
                                    onClick={() => onSelect(priority.id)}
                                    className="flex items-center gap-2 rounded-md border border-primary/50 bg-brown-800 p-3 text-left transition-colors hover:bg-brown-700"
                                >
                                    {priority.media && (
                                        <img src={priority.media} alt="" className="h-5 w-5 rounded-sm" />
                                    )}
                                    <span className="text-sm">{priority.title}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                ))}
                {Object.keys(groupedPriorities).length === 0 && (
                    <p className="py-4 text-center text-gray-400">All priorities have been assigned to this item.</p>
                )}
            </div>
        </div>
    );
}

function EditablePriorityDisplay({ priorities, allPriorities, data, setData }) {
    const [showPicker, setShowPicker] = useState(false);
    const [targetWeight, setTargetWeight] = useState(null);
    const [activeId, setActiveId] = useState(null);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        }),
        useSensor(KeyboardSensor),
    );

    const groupedPriorities = useMemo(() => {
        const sorted = [...priorities].sort((a, b) => a.weight - b.weight);
        return sorted.reduce((acc, priority) => {
            const weight = priority.weight;
            if (!acc[weight]) {
                acc[weight] = [];
            }
            acc[weight].push(priority);
            return acc;
        }, {});
    }, [priorities]);

    const weights = useMemo(() => {
        return Object.keys(groupedPriorities)
            .map(Number)
            .sort((a, b) => a - b);
    }, [groupedPriorities]);

    const maxWeight = useMemo(() => {
        return weights.length > 0 ? Math.max(...weights) : -1;
    }, [weights]);

    const availablePriorities = useMemo(() => {
        const assignedIds = new Set(data.priorities.map((p) => p.priority_id));
        return allPriorities.filter((p) => !assignedIds.has(p.id));
    }, [allPriorities, data.priorities]);

    const activePriority = useMemo(() => {
        if (!activeId) return null;
        return priorities.find((p) => p.id === activeId);
    }, [activeId, priorities]);

    const recalculateWeights = (updatedPriorities) => {
        const uniqueWeights = [...new Set(updatedPriorities.map((p) => p.weight))].sort((a, b) => a - b);
        return updatedPriorities.map((p) => ({
            ...p,
            weight: uniqueWeights.indexOf(p.weight),
        }));
    };

    const handleAddPriority = (priorityId) => {
        const priority = allPriorities.find((p) => p.id === priorityId);
        if (!priority) return;

        const newPriorities = [...data.priorities, { priority_id: priorityId, weight: targetWeight }];
        setData("priorities", newPriorities);
        setShowPicker(false);
        setTargetWeight(null);
    };

    const handleRemovePriority = (priorityId) => {
        const updated = data.priorities.filter((p) => p.priority_id !== priorityId);
        setData("priorities", recalculateWeights(updated));
    };

    const handleAddToWeight = (weight) => {
        setTargetWeight(weight);
        setShowPicker(true);
    };

    const handleInsertBetween = (afterWeight) => {
        const updated = data.priorities.map((p) => ({
            ...p,
            weight: p.weight > afterWeight ? p.weight + 1 : p.weight,
        }));
        setData("priorities", updated);
        setTargetWeight(afterWeight + 1);
        setShowPicker(true);
    };

    const handleAddNewWeight = (weight) => {
        setTargetWeight(weight);
        setShowPicker(true);
    };

    const handleDragStart = (event) => {
        setActiveId(event.active.id);
    };

    const handleDragEnd = (event) => {
        const { active, over } = event;
        setActiveId(null);

        if (!over) return;

        const priorityId = active.id;
        const overData = over.data.current;

        if (!overData) return;

        let newWeight;

        if (overData.type === "weight-row") {
            newWeight = overData.weight;
        } else if (overData.type === "insert-zone") {
            const afterWeight = overData.afterWeight;
            const updated = data.priorities.map((p) => ({
                ...p,
                weight: p.weight > afterWeight ? p.weight + 1 : p.weight,
            }));
            newWeight = afterWeight + 1;
            const finalPriorities = updated.map((p) =>
                p.priority_id === priorityId ? { ...p, weight: newWeight } : p,
            );
            setData("priorities", recalculateWeights(finalPriorities));
            return;
        } else if (overData.type === "new-weight") {
            newWeight = overData.weight;
        } else {
            return;
        }

        const updated = data.priorities.map((p) => (p.priority_id === priorityId ? { ...p, weight: newWeight } : p));
        setData("priorities", recalculateWeights(updated));
    };

    if (priorities.length === 0 && data.priorities.length === 0) {
        return (
            <div className="py-8 text-center">
                <p className="mb-4 text-gray-400">No priorities assigned to this item.</p>
                <button
                    type="button"
                    onClick={() => handleAddNewWeight(0)}
                    className="inline-flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-white transition-colors hover:bg-amber-700"
                >
                    <Icon icon="plus" style="solid" />
                    Add First Priority
                </button>
                <PriorityPickerModal
                    isOpen={showPicker}
                    onClose={() => {
                        setShowPicker(false);
                        setTargetWeight(null);
                    }}
                    priorities={availablePriorities}
                    onSelect={handleAddPriority}
                />
            </div>
        );
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
        >
            <div className="flex-col flex-wrap items-center gap-2 text-lg">
                {weights.map((weight, weightIndex) => (
                    <div key={weight}>
                        {weightIndex > 0 && (
                            <InsertWeightZone afterWeight={weights[weightIndex - 1]} onAddClick={handleInsertBetween} />
                        )}
                        <DroppableWeightRow weight={weight} onAddClick={handleAddToWeight}>
                            {groupedPriorities[weight].map((priority, index) => (
                                <div key={priority.id} className="flex items-center">
                                    {index > 0 && (
                                        <div className="mx-2 w-12 flex-none items-center text-center text-2xl font-bold text-amber-600">
                                            <Icon icon="equals" style="solid" className="-ml-4" />
                                        </div>
                                    )}
                                    <DraggablePriorityItem priority={priority} onRemove={handleRemovePriority} />
                                </div>
                            ))}
                        </DroppableWeightRow>
                    </div>
                ))}
                <div className="mt-8">
                    <AddNewWeightRow weight={maxWeight + 1} onAddClick={handleAddNewWeight} />
                </div>
            </div>
            <DragOverlay>
                <PriorityOverlay priority={activePriority} />
            </DragOverlay>
            <PriorityPickerModal
                isOpen={showPicker}
                onClose={() => {
                    setShowPicker(false);
                    setTargetWeight(null);
                }}
                priorities={availablePriorities}
                onSelect={handleAddPriority}
            />
        </DndContext>
    );
}

export default function ItemEdit({ item, allPriorities: allPrioritiesResource, comments }) {
    const allPriorities = allPrioritiesResource.data;

    const { data, setData, put, processing, isDirty } = useForm({
        priorities: item.data.priorities.map((p) => ({
            priority_id: p.id,
            weight: p.weight,
        })),
    });

    const isFirstRender = useRef(true);
    const debounceTimer = useRef(null);
    const [showSaved, setShowSaved] = useState(false);

    // Auto-save when priorities change
    useEffect(() => {
        // Skip initial render
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        // Clear existing timer
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        // Don't save if no changes
        if (!isDirty) return;

        // Debounced save
        debounceTimer.current = setTimeout(() => {
            put(route("loot.items.priorities.update", { item: item.data.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    setShowSaved(true);
                    setTimeout(() => setShowSaved(false), 2000);
                },
            });
        }, 1000);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [data.priorities]);

    const prioritiesWithDetails = useMemo(() => {
        return data.priorities.map((p) => {
            const priority = allPriorities.find((ap) => ap.id === p.priority_id);
            return {
                ...priority,
                weight: p.weight,
            };
        });
    }, [data.priorities, allPriorities]);

    return (
        <Master title={`Editing ${item.data.name}`}>
            <SharedHeader backgroundClass="bg-karazhan" title="Edit Loot Biases" />
            {/* Tool navigation */}
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto px-4">
                    <div className="flex min-h-12 flex-col items-center justify-between md:flex-row">
                        <div className="flex-initial space-x-4">
                            <Link
                                href={route("loot.items.show", { item: item.data.id })}
                                className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                            >
                                <Icon icon="arrow-left" style="solid" className="mr-2" />
                                <span>Finish editing {item.data.name}</span>
                            </Link>
                        </div>
                        <div className="flex space-x-4">
                            {processing && (
                                <span className="text-sm font-medium text-amber-400">
                                    <Icon icon="spinner" style="solid" className="fa-spin mr-2" />
                                    Saving...
                                </span>
                            )}
                            {!processing && showSaved && (
                                <span className="text-sm font-medium text-green-400">
                                    <Icon icon="check" style="solid" className="mr-2" />
                                    Saved
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </nav>
            {/* Content */}
            <main className="container mx-auto px-4 py-8">
                <ItemDetailsCard item={item.data} />

                {/* Editable Priorities */}
                <h2 className="mb-2 mt-8 text-xl font-bold">Loot Priorities</h2>
                <p className="mb-4 text-gray-400">
                    Drag priorities between rows to change their weight. Use the + buttons to add new priorities.
                </p>
                <div className="mt-8 w-full">
                    <EditablePriorityDisplay
                        priorities={prioritiesWithDetails}
                        allPriorities={allPriorities}
                        data={data}
                        setData={setData}
                    />
                </div>

                {/* Notes Section */}
                <Notes notes={item.data.notes} itemId={item.data.id} canEdit="true" />

                {/* Comments Section */}
                <CommentsSection comments={comments} itemId={item.data.id} canCreate="true" />
            </main>
        </Master>
    );
}
