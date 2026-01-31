import Master from '@/Layouts/Master';
import SharedHeader from '@/Components/SharedHeader';
import { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import {
    DndContext,
    DragOverlay,
    pointerWithin,
    PointerSensor,
    KeyboardSensor,
    useSensor,
    useSensors,
    useDroppable,
} from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
    arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

function SortableRankItem({ rank, index, onNameChange, isSavingName }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editedName, setEditedName] = useState(rank.name);
    const inputRef = useRef(null);
    const saveTimerRef = useRef(null);

    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: rank.id });

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
        if (e.key === 'Enter') {
            e.preventDefault();
            inputRef.current?.blur();
        } else if (e.key === 'Escape') {
            setEditedName(rank.name);
            setIsEditing(false);
        }
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="flex items-center justify-between border-b border-amber-600 last:border-b-0 px-4 py-2 bg-brown-900"
        >
            <span className="w-6 flex-initial mr-2 text-right">{index + 1}.</span>
            {isEditing ? (
                <input
                    ref={inputRef}
                    type="text"
                    value={editedName}
                    onChange={handleInputChange}
                    onBlur={handleInputBlur}
                    onKeyDown={handleKeyDown}
                    className="flex-auto text-left mr-2 bg-brown-800 border border-amber-600 rounded px-2 py-0.5 text-white focus:outline-none focus:ring-1 focus:ring-amber-500"
                />
            ) : (
                <span
                    className="flex-auto text-left mr-2 cursor-text hover:text-amber-400 transition-colors"
                    onClick={handleNameClick}
                    title="Click to edit"
                >
                    {rank.name}
                    <span className={isSavingName === rank.id ? 'inline' : 'hidden'}>
                        <i className="fas fa-spinner fa-spin ml-2 text-amber-400 text-xs"></i>
                    </span>
                </span>
            )}
            <span
                className="flex-none flex items-center justify-center cursor-grab"
                {...attributes}
                {...listeners}
            >
                <i className="far fa-grip-vertical text-grey-400 w-4 h-4"></i>
            </span>
        </div>
    );
}

function RankOverlay({ rank, index }) {
    if (!rank) return null;

    return (
        <div className="flex items-center justify-between border border-amber-600 px-4 py-2 bg-brown-900 shadow-lg cursor-grabbing rounded">
            <span className="w-6 flex-initial mr-2 text-right">{index + 1}.</span>
            <span className="flex-auto text-left mr-2">{rank.name}</span>
            <span className="flex-none flex items-center justify-center">
                <i className="far fa-grip-vertical text-grey-400 w-4 h-4"></i>
            </span>
        </div>
    );
}

function NewRankInput({ nextPosition, onSave, isSaving }) {
    const [name, setName] = useState('');
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
                setName('');
            }, 3000);
        }
    };

    const handleInputBlur = () => {
        if (saveTimerRef.current) {
            clearTimeout(saveTimerRef.current);
        }

        if (name.trim()) {
            onSave(name);
            setName('');
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (saveTimerRef.current) {
                clearTimeout(saveTimerRef.current);
            }
            if (name.trim()) {
                onSave(name);
                setName('');
            }
        } else if (e.key === 'Escape') {
            if (saveTimerRef.current) {
                clearTimeout(saveTimerRef.current);
            }
            setName('');
            inputRef.current?.blur();
        }
    };

    return (
        <div className="flex items-center justify-between border-t border-amber-600 px-4 py-2 bg-brown-800/50">
            <span className="w-6 flex-initial mr-2 text-right text-grey-400">
                {nextPosition + 1}.
            </span>
            <input
                ref={inputRef}
                type="text"
                value={name}
                onChange={handleInputChange}
                onBlur={handleInputBlur}
                onKeyDown={handleKeyDown}
                placeholder="Add new rank..."
                disabled={isSaving}
                className="flex-auto text-left mr-2 bg-transparent border-none px-2 py-0.5 text-grey-400 placeholder-grey-600 focus:outline-none focus:text-white"
            />
            {isSaving && (
                <span className="flex-none">
                    <i className="fas fa-spinner fa-spin text-amber-400 text-xs"></i>
                </span>
            )}
        </div>
    );
}

function TrashZone({ isVisible }) {
    const { setNodeRef, isOver } = useDroppable({
        id: 'trash',
    });

    if (!isVisible) return null;

    return (
        <div
            ref={setNodeRef}
            className={`mt-4 flex items-center justify-center gap-2 border-2 border-dashed rounded-lg p-4 transition-all duration-200 ${
                isOver
                    ? 'border-red-500 bg-red-500/20 text-red-400'
                    : 'border-grey-600 bg-grey-800/50 text-grey-400'
            }`}
        >
            <i className={`fas fa-trash-alt ${isOver ? 'text-red-400' : 'text-grey-500'}`}></i>
            <span className="text-sm font-medium">
                {isOver ? 'Release to delete' : 'Drag here to delete'}
            </span>
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
        useSensor(KeyboardSensor)
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
                route('dashboard.ranks.update-positions'),
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
                }
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

        if (over.id === 'trash') {
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
        router.delete(route('dashboard.ranks.destroy', rankId), {
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
            route('dashboard.ranks.store'),
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
            }
        );
    };

    const handleNameChange = (rankId, newName) => {
        setIsSavingName(rankId);
        router.put(
            route('dashboard.ranks.update', rankId),
            { name: newName },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setIsSavingName(null);
                    setRanks((prev) =>
                        prev.map((r) => (r.id === rankId ? { ...r, name: newName } : r))
                    );
                    setShowSaved(true);
                    setTimeout(() => setShowSaved(false), 2000);
                },
                onError: () => {
                    setIsSavingName(null);
                },
            }
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
                            <span className="text-amber-400 text-sm font-medium">
                                <i className="fas fa-spinner fa-spin mr-2"></i>
                                Saving...
                            </span>
                        )}
                        {!isSaving && showSaved && (
                            <span className="text-green-400 text-sm font-medium">
                                <i className="fas fa-check mr-2"></i>
                                Saved
                            </span>
                        )}
                    </div>
                    <div className="mt-6 w-64">
                        {ranks.length === 0 ? (
                            <div className="flex flex-col border border-amber-600 rounded">
                                <NewRankInput
                                    nextPosition={0}
                                    onSave={handleCreate}
                                    isSaving={isCreating}
                                />
                            </div>
                        ) : (
                            <DndContext
                                sensors={sensors}
                                collisionDetection={pointerWithin}
                                onDragStart={handleDragStart}
                                onDragEnd={handleDragEnd}
                            >
                                <SortableContext
                                    items={ranks.map((r) => r.id)}
                                    strategy={verticalListSortingStrategy}
                                >
                                    <div className="flex flex-col border border-amber-600 rounded">
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
