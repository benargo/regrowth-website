import { useState, useEffect, useRef } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";
import {
    DndContext,
    DragOverlay,
    pointerWithin,
    PointerSensor,
    KeyboardSensor,
    useSensor,
    useSensors,
    useDroppable,
} from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy, arrayMove } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";

function SortableRankItem({ rank, index, onNameChange, isSavingName }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editedName, setEditedName] = useState(rank.name);
    const inputRef = useRef(null);
    const saveTimerRef = useRef(null);

    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: rank.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    useEffect(() => {
        if (isEditing && inputRef.current) {
            inputRef.current.focus();
            inputRef.current.select();
        }
    }, [isEditing]);

    useEffect(() => {
        setEditedName(rank.name);
    }, [rank.name]);

    const handleNameClick = (e) => {
        e.stopPropagation();
        setIsEditing(true);
    };

    const handleInputChange = (e) => {
        const newName = e.target.value;
        setEditedName(newName);

        if (saveTimerRef.current) {
            clearTimeout(saveTimerRef.current);
        }

        saveTimerRef.current = setTimeout(() => {
            if (newName.trim() && newName !== rank.name) {
                onNameChange(rank.id, newName);
            }
        }, 3000);
    };

    const handleInputBlur = () => {
        if (saveTimerRef.current) {
            clearTimeout(saveTimerRef.current);
        }

        if (editedName.trim() && editedName !== rank.name) {
            onNameChange(rank.id, editedName);
        }

        setIsEditing(false);
    };

    const handleKeyDown = (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            inputRef.current?.blur();
        } else if (e.key === "Escape") {
            setEditedName(rank.name);
            setIsEditing(false);
        }
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="flex items-center justify-between border-b border-amber-600 bg-brown-900 px-4 py-2 last:border-b-0"
        >
            <span className="mr-2 w-6 flex-initial text-right">{index + 1}.</span>
            {isEditing ? (
                <input
                    ref={inputRef}
                    type="text"
                    value={editedName}
                    onChange={handleInputChange}
                    onBlur={handleInputBlur}
                    onKeyDown={handleKeyDown}
                    className="mr-2 flex-auto rounded border border-amber-600 bg-brown-800 px-2 py-0.5 text-left text-white focus:outline-none focus:ring-1 focus:ring-amber-500"
                />
            ) : (
                <span
                    className="mr-2 flex-auto cursor-text text-left transition-colors hover:text-amber-400"
                    onClick={handleNameClick}
                    title="Click to edit"
                >
                    {rank.name}
                    <span className={isSavingName === rank.id ? "inline" : "hidden"}>
                        <Icon icon="spinner" style="solid" className="fa-spin ml-2 text-xs text-amber-400" />
                    </span>
                </span>
            )}
            <span className="flex flex-none cursor-grab items-center justify-center" {...attributes} {...listeners}>
                <Icon icon="grip-vertical" style="regular" className="text-grey-400 h-4 w-4" />
            </span>
        </div>
    );
}

function RankOverlay({ rank, index }) {
    if (!rank) return null;

    return (
        <div className="flex cursor-grabbing items-center justify-between rounded border border-amber-600 bg-brown-900 px-4 py-2 shadow-lg">
            <span className="mr-2 w-6 flex-initial text-right">{index + 1}.</span>
            <span className="mr-2 flex-auto text-left">{rank.name}</span>
            <span className="flex flex-none items-center justify-center">
                <Icon icon="grip-vertical" style="regular" className="text-grey-400 h-4 w-4" />
            </span>
        </div>
    );
}

function NewRankInput({ nextPosition, onSave, isSaving }) {
    const [name, setName] = useState("");
    const inputRef = useRef(null);
    const saveTimerRef = useRef(null);

    const handleInputChange = (e) => {
        const newName = e.target.value;
        setName(newName);

        if (saveTimerRef.current) {
            clearTimeout(saveTimerRef.current);
        }

        if (newName.trim()) {
            saveTimerRef.current = setTimeout(() => {
                onSave(newName);
                setName("");
            }, 3000);
        }
    };

    const handleInputBlur = () => {
        if (saveTimerRef.current) {
            clearTimeout(saveTimerRef.current);
        }

        if (name.trim()) {
            onSave(name);
            setName("");
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            if (saveTimerRef.current) {
                clearTimeout(saveTimerRef.current);
            }
            if (name.trim()) {
                onSave(name);
                setName("");
            }
        } else if (e.key === "Escape") {
            if (saveTimerRef.current) {
                clearTimeout(saveTimerRef.current);
            }
            setName("");
            inputRef.current?.blur();
        }
    };

    return (
        <div className="flex items-center justify-between border-t border-amber-600 bg-brown-800/50 px-4 py-2">
            <span className="text-grey-400 mr-2 w-6 flex-initial text-right">{nextPosition + 1}.</span>
            <input
                ref={inputRef}
                type="text"
                value={name}
                onChange={handleInputChange}
                onBlur={handleInputBlur}
                onKeyDown={handleKeyDown}
                placeholder="Add new rank..."
                disabled={isSaving}
                className="text-grey-400 placeholder-grey-600 mr-2 flex-auto border-none bg-transparent px-2 py-0.5 text-left focus:text-white focus:outline-none"
            />
            {isSaving && (
                <span className="flex-none">
                    <Icon icon="spinner" style="solid" className="fa-spin text-xs text-amber-400" />
                </span>
            )}
        </div>
    );
}

function TrashZone({ isVisible }) {
    const { setNodeRef, isOver } = useDroppable({
        id: "trash",
    });

    if (!isVisible) return null;

    return (
        <div
            ref={setNodeRef}
            className={`mt-4 flex items-center justify-center gap-2 rounded-lg border-2 border-dashed p-4 transition-all duration-200 ${
                isOver ? "border-red-500 bg-red-500/20 text-red-400" : "border-grey-600 bg-grey-800/50 text-grey-400"
            }`}
        >
            <Icon icon="trash-alt" style="solid" className={isOver ? "text-red-400" : "text-grey-500"} />
            <span className="text-sm font-medium">{isOver ? "Release to delete" : "Drag here to delete"}</span>
        </div>
    );
}

export default function ManageRanks({ guildRanks: initialRanks }) {
    const [ranks, setRanks] = useState(initialRanks);
    const [activeId, setActiveId] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const [isSavingName, setIsSavingName] = useState(null);
    const [isCreating, setIsCreating] = useState(false);
    const [showSaved, setShowSaved] = useState(false);
    const isFirstRender = useRef(true);
    const debounceTimer = useRef(null);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        }),
        useSensor(KeyboardSensor),
    );

    const activeRank = activeId ? ranks.find((r) => r.id === activeId) : null;
    const activeIndex = activeRank ? ranks.indexOf(activeRank) : -1;

    // Auto-save when ranks order changes
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => {
            setIsSaving(true);
            router.post(
                route("dashboard.ranks.update-positions"),
                {
                    ranks: ranks.map((rank, index) => ({
                        id: rank.id,
                        position: index,
                    })),
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setIsSaving(false);
                        setShowSaved(true);
                        setTimeout(() => setShowSaved(false), 2000);
                    },
                    onError: () => {
                        setIsSaving(false);
                    },
                },
            );
        }, 500);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [ranks]);

    const handleDragStart = (event) => {
        setActiveId(event.active.id);
    };

    const handleDragEnd = (event) => {
        const { active, over } = event;
        setActiveId(null);

        if (!over) return;

        if (over.id === "trash") {
            handleDelete(active.id);
            return;
        }

        if (active.id === over.id) return;

        const oldIndex = ranks.findIndex((r) => r.id === active.id);
        const newIndex = ranks.findIndex((r) => r.id === over.id);

        setRanks(arrayMove(ranks, oldIndex, newIndex));
    };

    const handleDelete = (rankId) => {
        setIsSaving(true);
        router.delete(route("dashboard.ranks.destroy", rankId), {
            preserveScroll: true,
            onSuccess: () => {
                setRanks((prev) => prev.filter((r) => r.id !== rankId));
                setIsSaving(false);
                setShowSaved(true);
                setTimeout(() => setShowSaved(false), 2000);
            },
            onError: () => {
                setIsSaving(false);
            },
        });
    };

    const handleCreate = (name) => {
        setIsCreating(true);
        router.post(
            route("dashboard.ranks.store"),
            { name },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setIsCreating(false);
                    setRanks(page.props.guildRanks);
                    setShowSaved(true);
                    setTimeout(() => setShowSaved(false), 2000);
                },
                onError: () => {
                    setIsCreating(false);
                },
            },
        );
    };

    const handleNameChange = (rankId, newName) => {
        setIsSavingName(rankId);
        router.put(
            route("dashboard.ranks.update", rankId),
            { name: newName },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setIsSavingName(null);
                    setRanks((prev) => prev.map((r) => (r.id === rankId ? { ...r, name: newName } : r)));
                    setShowSaved(true);
                    setTimeout(() => setShowSaved(false), 2000);
                },
                onError: () => {
                    setIsSavingName(null);
                },
            },
        );
    };

    return (
        <Master title="Manage Guild Ranks">
            {/* Header */}
            <SharedHeader backgroundClass="bg-officer-meeting" title="Manage Guild Ranks" />
            {/* Content */}
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <div className="flex items-center gap-4">
                        <p className="text-grey-200">Drag and drop to reorder guild ranks.</p>
                        {isSaving && (
                            <span className="text-sm font-medium text-amber-400">
                                <Icon icon="spinner" style="solid" className="fa-spin mr-2" />
                                Saving...
                            </span>
                        )}
                        {!isSaving && showSaved && (
                            <span className="text-sm font-medium text-green-400">
                                <Icon icon="check" style="solid" className="mr-2" />
                                Saved
                            </span>
                        )}
                    </div>
                    <div className="mt-6 w-64">
                        {ranks.length === 0 ? (
                            <div className="flex flex-col rounded border border-amber-600">
                                <NewRankInput nextPosition={0} onSave={handleCreate} isSaving={isCreating} />
                            </div>
                        ) : (
                            <DndContext
                                sensors={sensors}
                                collisionDetection={pointerWithin}
                                onDragStart={handleDragStart}
                                onDragEnd={handleDragEnd}
                            >
                                <SortableContext items={ranks.map((r) => r.id)} strategy={verticalListSortingStrategy}>
                                    <div className="flex flex-col rounded border border-amber-600">
                                        {ranks.map((rank, index) => (
                                            <SortableRankItem
                                                key={rank.id}
                                                rank={rank}
                                                index={index}
                                                onNameChange={handleNameChange}
                                                isSavingName={isSavingName}
                                            />
                                        ))}
                                        {ranks.length < 10 && (
                                            <NewRankInput
                                                nextPosition={ranks.length}
                                                onSave={handleCreate}
                                                isSaving={isCreating}
                                            />
                                        )}
                                    </div>
                                </SortableContext>
                                <DragOverlay>
                                    <RankOverlay rank={activeRank} index={activeIndex} />
                                </DragOverlay>
                                <TrashZone isVisible={activeId !== null} />
                            </DndContext>
                        )}
                    </div>
                </div>
            </div>
        </Master>
    );
}
