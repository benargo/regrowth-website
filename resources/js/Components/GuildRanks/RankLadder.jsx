import { useRef, useState } from "react";
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import Checkbox from "@/Components/Checkbox";
import Icon from "@/Components/FontAwesome/Icon";
import { buttonClassName, controlClassName } from "@/Components/FormControls";

/**
 * The rank's in-game colour, from the --color-guild-rank-{slug} theme
 * variables, falling back to the default rank colour for ranks without one.
 */
function rankColour(slug) {
    return `var(--color-guild-rank-${slug}, var(--color-guild-rank, #1f8b4c))`;
}

function charactersLabel(count) {
    if (count === 0) {
        return "No characters";
    }

    return count === 1 ? "1 character" : `${count} characters`;
}

function RankRowContent({ row, index, dragHandle, children }) {
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-2 sm:flex-nowrap">
            {dragHandle}
            <span aria-hidden="true" className="text-secondary-400 w-7 text-right font-serif text-2xl tabular-nums">
                {index}
            </span>
            <span
                aria-hidden="true"
                className="h-8 w-1 shrink-0 rounded-full"
                style={{ backgroundColor: rankColour(row.slug) }}
            />
            {children}
        </div>
    );
}

function SortableRankRow({ row, index, error, onRename, onCommitName, onToggleAttendance, onDelete }) {
    const nameBeforeEdit = useRef(row.name);
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
        id: row.key,
    });
    const nameId = `guild-rank-${row.key}-name`;
    const attendanceId = `guild-rank-${row.key}-attendance`;

    function handleBlur() {
        if (row.name.trim() === "") {
            onRename(row.key, nameBeforeEdit.current);
            return;
        }

        onCommitName();
    }

    function handleKeyDown(event) {
        if (event.key === "Enter") {
            event.preventDefault();
            event.currentTarget.blur();
        } else if (event.key === "Escape") {
            onRename(row.key, nameBeforeEdit.current);
            event.currentTarget.blur();
        }
    }

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={`bg-ground-900 ${isDragging ? "opacity-40" : ""}`}
        >
            <RankRowContent
                row={row}
                index={index}
                dragHandle={
                    <button
                        type="button"
                        ref={setActivatorNodeRef}
                        {...attributes}
                        {...listeners}
                        aria-label={`Move ${row.name}`}
                        className="text-secondary-400 focus-visible:outline-ink-400 flex h-8 w-6 cursor-grab items-center justify-center rounded hover:text-white focus-visible:outline-2 active:cursor-grabbing"
                    >
                        <Icon icon="grip-vertical" style="regular" />
                    </button>
                }
            >
                <input
                    id={nameId}
                    type="text"
                    value={row.name}
                    maxLength={255}
                    aria-label={`Rank ${index} name`}
                    aria-invalid={error ? true : undefined}
                    onFocus={() => {
                        nameBeforeEdit.current = row.name;
                    }}
                    onChange={(event) => onRename(row.key, event.target.value)}
                    onBlur={handleBlur}
                    onKeyDown={handleKeyDown}
                    className="focus-visible:outline-ink-400 hover:border-ink-600 min-w-0 flex-1 basis-40 rounded border-b border-transparent bg-transparent px-1 py-1 text-base text-white focus-visible:outline-2"
                />
                <span className="text-secondary-400 w-28 text-sm">{charactersLabel(row.characters_count)}</span>
                <span className="flex items-center gap-2">
                    <Checkbox
                        id={attendanceId}
                        checked={row.count_attendance}
                        onChange={() => onToggleAttendance(row.key)}
                    />
                    <label htmlFor={attendanceId} className="text-secondary-300 text-sm whitespace-nowrap">
                        Attendance tracked
                    </label>
                </span>
                <button
                    type="button"
                    onClick={() => onDelete(row.key)}
                    aria-label={`Delete ${row.name}`}
                    className="text-secondary-400 focus-visible:outline-ink-400 ml-auto flex h-8 w-8 items-center justify-center rounded hover:text-red-400 focus-visible:text-red-400 focus-visible:outline-2"
                >
                    <Icon icon="trash-alt" style="solid" />
                </button>
            </RankRowContent>
            {error && (
                <p className="px-3 pb-2 pl-24 text-sm text-red-400" role="alert">
                    {error}
                </p>
            )}
        </li>
    );
}

function AddRankRow({ onAdd }) {
    const [name, setName] = useState("");

    function add() {
        const trimmedName = name.trim();

        if (trimmedName === "") {
            return;
        }

        onAdd(trimmedName);
        setName("");
    }

    return (
        <div className="bg-ground-800/50 flex flex-wrap items-center gap-3 px-3 py-3">
            <label htmlFor="guild-rank-new-name" className="sr-only">
                New rank name
            </label>
            <input
                id="guild-rank-new-name"
                type="text"
                value={name}
                maxLength={255}
                placeholder="Rank name"
                onChange={(event) => setName(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === "Enter") {
                        event.preventDefault();
                        add();
                    }
                }}
                className={`${controlClassName} min-w-0 flex-1 basis-48`}
            />
            <button type="button" onClick={add} className={buttonClassName}>
                <Icon icon="plus" className="mr-2" />
                Add rank
            </button>
        </div>
    );
}

/**
 * A guild's ranks in Blizzard rank order, each row numbered with its 0-based
 * rank index and marked with its in-game colour. Rows can be dragged (or
 * moved with the keyboard from their handle), renamed in place, toggled for
 * attendance and deleted. The parent owns the rows: onChange updates them
 * while typing, and onCommit updates them and saves. Both take an updater
 * that receives the parent's latest rows, which may be newer than `rows` when
 * a save lands between renders. `errors` maps a row's key to its message.
 */
export default function RankLadder({ rows, onChange, onCommit, errors = {}, maxRanks, newRow }) {
    const [activeKey, setActiveKey] = useState(null);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );
    const activeIndex = rows.findIndex((row) => row.key === activeKey);

    function rename(key, name) {
        onChange((current) => current.map((row) => (row.key === key ? { ...row, name } : row)));
    }

    function toggleAttendance(key) {
        onCommit((current) =>
            current.map((row) => (row.key === key ? { ...row, count_attendance: !row.count_attendance } : row)),
        );
    }

    function remove(key) {
        onCommit((current) => current.filter((row) => row.key !== key));
    }

    function handleDragEnd({ active, over }) {
        setActiveKey(null);

        if (!over || active.id === over.id) {
            return;
        }

        onCommit((current) =>
            arrayMove(
                current,
                current.findIndex((row) => row.key === active.id),
                current.findIndex((row) => row.key === over.id),
            ),
        );
    }

    return (
        <div className="border-ink-600/40 overflow-hidden rounded border">
            {rows.length === 0 ? (
                <p className="text-secondary-300 px-4 py-3 text-sm">No ranks yet. Add the guild master first.</p>
            ) : (
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragStart={({ active }) => setActiveKey(active.id)}
                    onDragCancel={() => setActiveKey(null)}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext items={rows.map((row) => row.key)} strategy={verticalListSortingStrategy}>
                        <ol aria-label="Guild ranks" className="divide-ink-600/40 divide-y">
                            {rows.map((row, index) => (
                                <SortableRankRow
                                    key={row.key}
                                    row={row}
                                    index={index}
                                    error={errors[row.key]}
                                    onRename={rename}
                                    onCommitName={() => onCommit((current) => current)}
                                    onToggleAttendance={toggleAttendance}
                                    onDelete={remove}
                                />
                            ))}
                        </ol>
                    </SortableContext>
                    <DragOverlay>
                        {activeIndex >= 0 && (
                            <div className="bg-ground-900 ring-ink-400 rounded shadow-lg ring-1">
                                <RankRowContent
                                    row={rows[activeIndex]}
                                    index={activeIndex}
                                    dragHandle={
                                        <span className="text-secondary-400 flex h-8 w-6 items-center justify-center">
                                            <Icon icon="grip-vertical" style="regular" />
                                        </span>
                                    }
                                >
                                    <span className="flex-1 px-1 text-white">{rows[activeIndex].name}</span>
                                </RankRowContent>
                            </div>
                        )}
                    </DragOverlay>
                </DndContext>
            )}
            <div className="border-ink-600/40 border-t">
                {rows.length < maxRanks ? (
                    <AddRankRow onAdd={(name) => onCommit((current) => [...current, newRow(name)])} />
                ) : (
                    <p className="text-secondary-300 px-4 py-3 text-sm">Guilds can have up to {maxRanks} ranks.</p>
                )}
            </div>
        </div>
    );
}
