import Master from '@/Layouts/Master';
import { useState, useMemo, useEffect, useRef } from 'react';
import { router, Link, useForm } from '@inertiajs/react';
import {
    DndContext,
    DragOverlay,
    closestCenter,
    PointerSensor,
    KeyboardSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useDroppable } from '@dnd-kit/core';

function DraggablePriorityItem({ priority, onRemove }) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: priority.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="relative min-w-50 flex items-center justify-center gap-2 p-6 border border-primary rounded-md bg-brown-800 cursor-grab"
            {...attributes}
            {...listeners}
        >
            {priority.media && (
                <img src={priority.media} alt="" className="w-6 h-6 rounded-sm" />
            )}
            <span>{priority.title}</span>
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    onRemove(priority.id);
                }}
                className="absolute -top-2 -right-2 w-6 h-6 flex items-center justify-center bg-red-600 hover:bg-red-700 text-white rounded-full text-xs transition-colors"
            >
                <i className="fas fa-times"></i>
            </button>
        </div>
    );
}

function PriorityOverlay({ priority }) {
    if (!priority) return null;

    return (
        <div className="w-60 flex items-center justify-center gap-2 p-6 border border-primary rounded-md bg-brown-800 shadow-lg cursor-grabbing">
            {priority.media && (
                <img src={priority.media} alt="" className="w-6 h-6 rounded-sm" />
            )}
            <span>{priority.title}</span>
        </div>
    );
}

function DroppableWeightRow({ weight, children, onAddClick }) {
    const { setNodeRef, isOver } = useDroppable({
        id: `weight-${weight}`,
        data: { weight, type: 'weight-row' },
    });

    return (
        <div
            ref={setNodeRef}
            className={`flex items-center justify-center transition-colors ${isOver ? 'bg-amber-900/30' : ''}`}
        >
            <div className="w-12 flex-none text-4xl">{weight + 1}</div>
            <div className="w-full flex items-center justify-center flex-wrap gap-4 py-4 ml-4">
                {children}
                <button
                    type="button"
                    onClick={() => onAddClick(weight)}
                    className="w-12 h-12 flex items-center justify-center bg-amber-600 hover:bg-amber-700 text-white rounded-full transition-colors"
                >
                    <i className="fas fa-plus"></i>
                </button>
            </div>
        </div>
    );
}

function InsertWeightZone({ afterWeight, onDrop, onAddClick }) {
    const [isHovered, setIsHovered] = useState(false);
    const { setNodeRef, isOver } = useDroppable({
        id: `insert-${afterWeight}`,
        data: { afterWeight, type: 'insert-zone' },
    });

    return (
        <div
            ref={setNodeRef}
            className="relative my-2"
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <div className="font-bold text-4xl text-center text-amber-600 my-4">
                <i className="fas fa-chevron-down"></i>
            </div>
            <div
                className={`absolute inset-x-0 top-1/2 -translate-y-1/2 flex items-center justify-center transition-opacity ${
                    isHovered || isOver ? 'opacity-100' : 'opacity-0'
                }`}
            >
                <button
                    type="button"
                    onClick={() => onAddClick(afterWeight)}
                    className={`w-10 h-10 flex items-center justify-center rounded-full transition-colors ${
                        isOver
                            ? 'bg-amber-500 text-white'
                            : 'bg-amber-600 hover:bg-amber-700 text-white'
                    }`}
                >
                    <i className="fas fa-plus"></i>
                </button>
            </div>
        </div>
    );
}

function AddNewWeightRow({ weight, onAddClick }) {
    const { setNodeRef, isOver } = useDroppable({
        id: `new-weight-${weight}`,
        data: { weight, type: 'new-weight' },
    });

    return (
        <div
            ref={setNodeRef}
            className={`flex items-center justify-center py-8 border-2 border-dashed rounded-lg transition-colors ${
                isOver ? 'border-amber-500 bg-amber-900/20' : 'border-amber-600/30'
            }`}
        >
            <button
                type="button"
                onClick={() => onAddClick(weight)}
                className="w-12 h-12 flex items-center justify-center bg-amber-600 hover:bg-amber-700 text-white rounded-full transition-colors"
            >
                <i className="fas fa-plus"></i>
            </button>
            <span className="ml-4 text-gray-400">Add new priority level</span>
        </div>
    );
}

function PriorityPickerModal({ isOpen, onClose, priorities, onSelect }) {
    if (!isOpen) return null;

    const groupedPriorities = useMemo(() => {
        return priorities.reduce((acc, priority) => {
            const type = priority.type || 'other';
            if (!acc[type]) {
                acc[type] = [];
            }
            acc[type].push(priority);
            return acc;
        }, {});
    }, [priorities]);

    const typeLabels = {
        role: 'Roles',
        class: 'Classes',
        spec: 'Specializations',
        other: 'Other',
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div
                className="bg-brown-900 border border-primary rounded-lg p-6 max-w-2xl max-h-[80vh] overflow-y-auto"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-xl font-bold">Select Priority</h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 hover:text-white"
                    >
                        <i className="fas fa-times"></i>
                    </button>
                </div>
                {Object.entries(groupedPriorities).map(([type, typePriorities]) => (
                    <div key={type} className="mb-4">
                        <h4 className="text-sm font-semibold text-amber-500 uppercase mb-2">
                            {typeLabels[type] || type}
                        </h4>
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            {typePriorities.map((priority) => (
                                <button
                                    key={priority.id}
                                    type="button"
                                    onClick={() => onSelect(priority.id)}
                                    className="flex items-center gap-2 p-3 border border-primary/50 rounded-md bg-brown-800 hover:bg-brown-700 transition-colors text-left"
                                >
                                    {priority.media && (
                                        <img src={priority.media} alt="" className="w-5 h-5 rounded-sm" />
                                    )}
                                    <span className="text-sm">{priority.title}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                ))}
                {Object.keys(groupedPriorities).length === 0 && (
                    <p className="text-gray-400 text-center py-4">
                        All priorities have been assigned to this item.
                    </p>
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
        useSensor(KeyboardSensor)
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
        return Object.keys(groupedPriorities).map(Number).sort((a, b) => a - b);
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

        const newPriorities = [
            ...data.priorities,
            { priority_id: priorityId, weight: targetWeight },
        ];
        setData('priorities', newPriorities);
        setShowPicker(false);
        setTargetWeight(null);
    };

    const handleRemovePriority = (priorityId) => {
        const updated = data.priorities.filter((p) => p.priority_id !== priorityId);
        setData('priorities', recalculateWeights(updated));
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
        setData('priorities', updated);
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

        if (overData.type === 'weight-row') {
            newWeight = overData.weight;
        } else if (overData.type === 'insert-zone') {
            const afterWeight = overData.afterWeight;
            const updated = data.priorities.map((p) => ({
                ...p,
                weight: p.weight > afterWeight ? p.weight + 1 : p.weight,
            }));
            newWeight = afterWeight + 1;
            const finalPriorities = updated.map((p) =>
                p.priority_id === priorityId ? { ...p, weight: newWeight } : p
            );
            setData('priorities', recalculateWeights(finalPriorities));
            return;
        } else if (overData.type === 'new-weight') {
            newWeight = overData.weight;
        } else {
            return;
        }

        const updated = data.priorities.map((p) =>
            p.priority_id === priorityId ? { ...p, weight: newWeight } : p
        );
        setData('priorities', recalculateWeights(updated));
    };

    if (priorities.length === 0 && data.priorities.length === 0) {
        return (
            <div className="text-center py-8">
                <p className="text-gray-400 mb-4">No priorities assigned to this item.</p>
                <button
                    type="button"
                    onClick={() => handleAddNewWeight(0)}
                    className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-md transition-colors"
                >
                    <i className="fas fa-plus"></i>
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
            <div className="text-lg flex-col items-center flex-wrap gap-2">
                {weights.map((weight, weightIndex) => (
                    <div key={weight}>
                        {weightIndex > 0 && (
                            <InsertWeightZone
                                afterWeight={weights[weightIndex - 1]}
                                onAddClick={handleInsertBetween}
                            />
                        )}
                        <DroppableWeightRow
                            weight={weight}
                            onAddClick={handleAddToWeight}
                        >
                            {groupedPriorities[weight].map((priority, index) => (
                                <div key={priority.id} className="flex items-center">
                                    {index > 0 && (
                                        <div className="w-12 flex-none items-center text-center font-bold text-2xl text-amber-600 mx-2">
                                            <i className="fas fa-equals -ml-4"></i>
                                        </div>
                                    )}
                                    <DraggablePriorityItem
                                        priority={priority}
                                        onRemove={handleRemovePriority}
                                    />
                                </div>
                            ))}
                        </DroppableWeightRow>
                    </div>
                ))}
                <div className="mt-8">
                    <AddNewWeightRow
                        weight={maxWeight + 1}
                        onAddClick={handleAddNewWeight}
                    />
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

export default function ItemEdit({ item, allPriorities: allPrioritiesResource }) {
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
            put(route('loot.items.priorities.update', { item: item.data.id }), {
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
        <Master title={`Edit ${item.data.name}`}>
            {/* Header */}
            <div className="bg-karazhan py-24 text-white">
                <div className="container mx-auto px-4">
                    <h1 className="text-4xl font-bold text-center">
                        Edit Loot Priorities
                    </h1>
                </div>
            </div>
            {/* Tool navigation */}
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto px-4">
                    <div className="flex items-center justify-between h-12">
                        <div className="flex flex-1 space-x-4">
                            <Link
                                href={route('loot.index', { raid_id: item.data.raid.id })}
                                className="text-white hover:bg-brown-800 px-3 py-2 rounded-md text-sm font-medium"
                            >
                                <i className="fas fa-arrow-left mr-2"></i>
                                Back to {item.data.raid.name} loot
                            </Link>
                        </div>
                        <div className="flex items-center space-x-4">
                            {processing && (
                                <span className="text-amber-400 text-sm font-medium">
                                    <i className="fas fa-spinner fa-spin mr-2"></i>
                                    Saving...
                                </span>
                            )}
                            {!processing && showSaved && (
                                <span className="text-green-400 text-sm font-medium">
                                    <i className="fas fa-check mr-2"></i>
                                    Saved
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </nav>
            {/* Content */}
            <main className="container mx-auto px-4 py-8">
                <div>
                    <div className="flex flex-row items-start space-x-8">
                        <div className="flex-none w-24 h-24 mb-8">
                            <Link href={item.data.wowhead_url} data-wowhead={`item=${item.data.id}&domain=tbc`} target="_blank" rel="noopener noreferrer">
                                <img
                                    src={item.data.icon}
                                    alt={item.data.name}
                                    className="w-24 h-24 rounded-lg box-shadow"
                                />
                            </Link>
                        </div>
                        <div className="w-64 flex-auto">
                            {/* Item Details */}
                            <h2 className={`text-2xl font-bold mb-4 text-quality-${item.data.quality?.name?.toLowerCase() || 'common'}`}>{item.data.name}</h2>
                            {item.data.item_class && <p className="mb-2"><strong>Type:</strong> {item.data.item_class}{item.data.item_subclass ? ` / ${item.data.item_subclass}` : ''}</p>}
                            {item.data.inventory_type && <p className="mb-2"><strong>Slot:</strong> {item.data.inventory_type}</p>}
                            {item.data.boss && <p className="mb-2"><strong>Drops from:</strong> {item.data.boss.name}</p>}
                        </div>
                        {/* Wowhead Link */}
                        <div className="w-32 flex-auto text-right">
                            <Link
                                href={item.data.wowhead_url}
                                data-wowhead={`item=${item.data.id}&domain=tbc`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-block bg-wowhead text-white px-4 py-2 rounded-md font-medium hover:opacity-90 transition-opacity"
                            >
                                <img src="/images/logo_wowhead_white.webp" alt="Wowhead Logo" className="inline-block w-5 h-5 mr-2 -mt-1" />
                                View on Wowhead
                            </Link>
                        </div>
                    </div>
                    <h2 className="text-xl font-bold mt-8 mb-4">Loot Priorities</h2>
                    <p className="text-gray-400 mb-4">
                        Drag priorities between rows to change their weight. Use the + buttons to add new priorities.
                    </p>
                    {/* Editable Priorities */}
                    <div className="mt-8 w-full">
                        <EditablePriorityDisplay
                            priorities={prioritiesWithDetails}
                            allPriorities={allPriorities}
                            data={data}
                            setData={setData}
                        />
                    </div>
                </div>
            </main>
        </Master>
    );
}
