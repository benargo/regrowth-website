import { useEffect, useRef, useState } from "react";
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
import Icon from "@/Components/FontAwesome/Icon";

/**
 * The list with the item keyed `activeKey` moved to where `overKey` is.
 */
export function moveByKey(list, getKey, activeKey, overKey) {
    return arrayMove(
        list,
        list.findIndex((item) => getKey(item) === activeKey),
        list.findIndex((item) => getKey(item) === overKey),
    );
}

/**
 * The grip that drags an item, or moves it with the arrow keys once focused
 * and picked up with Space.
 */
function DragHandle({ label, attributes, listeners, setActivatorNodeRef }) {
    return (
        <button
            type="button"
            ref={setActivatorNodeRef}
            {...attributes}
            {...listeners}
            aria-label={`Move ${label}`}
            className="flex h-8 w-6 shrink-0 cursor-grab items-center justify-center rounded text-gray-400 hover:text-white focus-visible:outline-2 focus-visible:outline-amber-400 active:cursor-grabbing"
        >
            <Icon icon="grip-vertical" style="regular" />
        </button>
    );
}

const MOVE_BUTTON_CLASS_NAME =
    "flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-white focus-visible:outline-2 focus-visible:outline-amber-400 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-gray-400";

/**
 * Buttons that move an item one place up or down with a single click. Focus
 * follows the item to its new place, or moves to the other button once the
 * item reaches the top or bottom.
 */
function MoveButtons({ label, index, count, onMoveBy }) {
    const upButton = useRef(null);
    const downButton = useRef(null);
    const pendingFocus = useRef(null);

    useEffect(() => {
        const [preferred, fallback] = pendingFocus.current === "up" ? [upButton, downButton] : [downButton, upButton];

        if (pendingFocus.current !== null) {
            (preferred.current?.disabled ? fallback : preferred).current?.focus();
            pendingFocus.current = null;
        }
    }, [index]);

    function moveBy(offset, direction) {
        pendingFocus.current = direction;
        onMoveBy(offset);
    }

    return (
        <span className="flex shrink-0 flex-col">
            <button
                type="button"
                ref={upButton}
                disabled={index === 0}
                onClick={() => moveBy(-1, "up")}
                aria-label={`Move ${label} up`}
                className={MOVE_BUTTON_CLASS_NAME}
            >
                <Icon icon="arrow-up" style="regular" className="text-xs" />
            </button>
            <button
                type="button"
                ref={downButton}
                disabled={index === count - 1}
                onClick={() => moveBy(1, "down")}
                aria-label={`Move ${label} down`}
                className={MOVE_BUTTON_CLASS_NAME}
            >
                <Icon icon="arrow-down" style="regular" className="text-xs" />
            </button>
        </span>
    );
}

function SortableItem({ item, index, count, getKey, getLabel, renderItem, itemClassName, onMoveBy }) {
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
        id: getKey(item),
    });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={`${itemClassName} ${isDragging ? "opacity-40" : ""}`}
        >
            {renderItem(item, {
                index,
                isDragging,
                dragHandle: (
                    <span className="flex shrink-0 items-center gap-1">
                        <DragHandle
                            label={getLabel(item)}
                            attributes={attributes}
                            listeners={listeners}
                            setActivatorNodeRef={setActivatorNodeRef}
                        />
                        <MoveButtons
                            label={getLabel(item)}
                            index={index}
                            count={count}
                            onMoveBy={(offset) => onMoveBy(item, offset)}
                        />
                    </span>
                ),
            })}
        </li>
    );
}

/**
 * An ordered list whose items can be dragged, moved with the keyboard from
 * their handle, or moved one place with their up and down buttons. Screen
 * readers hear each item's label and position as it moves. The parent owns
 * the order: onMove(activeKey, overKey) reports a move, and moveByKey()
 * applies it. renderItem receives each item with its
 * index and the drag handle to place; renderOverlay draws the item while it is
 * dragged (defaults to renderItem without a live handle).
 */
export default function SortableList({
    items,
    getKey,
    getLabel,
    label,
    onMove,
    renderItem,
    renderOverlay,
    className = "",
    itemClassName = "",
}) {
    const [activeKey, setActiveKey] = useState(null);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );
    const activeIndex = items.findIndex((item) => getKey(item) === activeKey);
    const overlay = renderOverlay ?? ((item, index) => renderItem(item, { index, isDragging: true, dragHandle: null }));

    const labelOf = (key) => getLabel(items.find((item) => getKey(item) === key));
    const positionOf = (key) => `position ${items.findIndex((item) => getKey(item) === key) + 1} of ${items.length}`;
    const announcements = {
        onDragStart: ({ active }) => `Picked up ${labelOf(active.id)}, in ${positionOf(active.id)}.`,
        onDragOver: ({ active, over }) =>
            over
                ? `${labelOf(active.id)} is over ${positionOf(over.id)}.`
                : `${labelOf(active.id)} is outside the list.`,
        onDragEnd: ({ active, over }) =>
            over
                ? `Dropped ${labelOf(active.id)} in ${positionOf(over.id)}.`
                : `Dropped ${labelOf(active.id)} outside the list. It stays where it was.`,
        onDragCancel: ({ active }) => `Moving ${labelOf(active.id)} was cancelled. It stays where it was.`,
    };

    function moveBy(item, offset) {
        const target = items[items.findIndex((candidate) => getKey(candidate) === getKey(item)) + offset];

        if (target) {
            onMove(getKey(item), getKey(target));
        }
    }

    function handleDragEnd({ active, over }) {
        setActiveKey(null);

        if (over && active.id !== over.id) {
            onMove(active.id, over.id);
        }
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            accessibility={{ announcements }}
            onDragStart={({ active }) => setActiveKey(active.id)}
            onDragCancel={() => setActiveKey(null)}
            onDragEnd={handleDragEnd}
        >
            <SortableContext items={items.map(getKey)} strategy={verticalListSortingStrategy}>
                <ol aria-label={label} className={className}>
                    {items.map((item, index) => (
                        <SortableItem
                            key={getKey(item)}
                            item={item}
                            index={index}
                            count={items.length}
                            getKey={getKey}
                            getLabel={getLabel}
                            renderItem={renderItem}
                            itemClassName={itemClassName}
                            onMoveBy={moveBy}
                        />
                    ))}
                </ol>
            </SortableContext>
            <DragOverlay>
                {activeIndex >= 0 && (
                    <div className="rounded shadow-lg ring-1 ring-amber-400">
                        {overlay(items[activeIndex], activeIndex)}
                    </div>
                )}
            </DragOverlay>
        </DndContext>
    );
}
