import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    useDroppable,
} from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Link, router, useForm } from "@inertiajs/react";
import FilterDropdown from "@/Components/FilterDropdown";
import AutoSaveLabel from "@/Components/AutoSaveLabel";
import Collapsible from "@/Components/Collapsible";
import AssignmentCellEditor, { resetAssignmentOptionsFetched } from "@/Components/Events/AssignmentCellEditor";
import MetaCard, { MetaItem } from "@/Components/MetaCard";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import Tooltip from "@/Components/Tooltip";
import ToolNav from "@/Components/ToolNav";
import Master from "@/Layouts/Master";
import { labelFromSide, storageFromSide, colorClassFromSide, textClassFromSide } from "@/Helpers/AssignmentCellHelpers";

function parseAddNewGroupId(id) {
    const m = String(id).match(/^drop-boss-(.+)-add-new-group$/);
    if (!m) return null;
    return { boss_id: m[1] === "event" ? null : Number(m[1]) };
}

// ─── Sortable assignment row ──────────────────────────────────────────────────

function AssignmentRowEditor({ assignment, targetMarkers, onUpdate, onRemove }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: assignment._key,
        data: { type: "assignment", boss_id: assignment.boss_id, group_id: assignment.group_id },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.3 : 1,
    };

    const leftLabel = labelFromSide(assignment._leftSide);
    const rightLabel = labelFromSide(assignment._rightSide);
    const leftColor = colorClassFromSide(assignment._leftSide);
    const rightColor = colorClassFromSide(assignment._rightSide);
    const leftText = textClassFromSide(assignment._leftSide);
    const rightText = textClassFromSide(assignment._rightSide);

    return (
        <tr
            ref={setNodeRef}
            style={style}
            data-assignment-key={assignment._key}
            className="group relative border-b border-brown-700/50 last:border-0"
        >
            <td
                className="w-6 cursor-grab px-1 py-2 text-center text-brown-700 hover:text-brown-400 active:cursor-grabbing"
                {...attributes}
                {...listeners}
            >
                <Icon icon="grip-vertical" style="solid" className="text-xs" />
            </td>
            <AssignmentCellEditor
                initialLabel={leftLabel.label}
                initialIconUrl={leftLabel.iconUrl}
                initialSlug={leftLabel.slug}
                colorClass={leftColor}
                textClass={leftText}
                targetMarkers={targetMarkers}
                onSelect={(val) =>
                    onUpdate({
                        ...assignment,
                        left_type: val.left_type,
                        left_value: val.left_value,
                        _leftSide: val.side ?? null,
                        _changedSide: "left",
                    })
                }
            />
            <AssignmentCellEditor
                initialLabel={rightLabel.label}
                initialIconUrl={rightLabel.iconUrl}
                initialSlug={rightLabel.slug}
                colorClass={rightColor}
                textClass={rightText}
                targetMarkers={targetMarkers}
                onSelect={(val) =>
                    onUpdate({
                        ...assignment,
                        right_type: val.left_type,
                        right_value: val.left_value,
                        _rightSide: val.side ?? null,
                        _changedSide: "right",
                    })
                }
            />
            <td className="w-10 px-1 py-2">
                <div className="relative flex h-full items-center justify-center">
                    <button
                        type="button"
                        onClick={() => onRemove(assignment._key)}
                        className="flex h-5 w-5 items-center justify-center rounded-full bg-red-700/80 text-xs text-white transition-colors hover:bg-red-600"
                    >
                        <Icon icon="times" style="solid" className="text-[9px]" />
                    </button>
                </div>
            </td>
        </tr>
    );
}

// ─── Add-row ──────────────────────────────────────────────────────────────────

function AddAssignmentRow({ onAdd }) {
    return (
        <tr className="border-t border-dashed border-brown-700/50">
            <td colSpan={4}>
                <button
                    type="button"
                    onClick={onAdd}
                    className="flex w-full items-center justify-center gap-2 py-2 text-xs text-brown-500 transition-colors hover:bg-brown-700/30 hover:text-brown-300"
                >
                    <Icon icon="plus" style="solid" className="text-[10px]" />
                    Add assignment
                </button>
            </td>
        </tr>
    );
}

function InsertionIndicatorRow() {
    return (
        <tr>
            <td colSpan={4} className="p-0">
                <div className="mx-1 flex h-8 items-center rounded border-2 border-dashed border-amber-500 bg-amber-900/20">
                    <span className="ml-3 text-[10px] font-medium text-amber-400">Drop here</span>
                </div>
            </td>
        </tr>
    );
}

// ─── Droppable assignment group editor ───────────────────────────────────────

function EmptyGroupDroppable({ containerId }) {
    const { setNodeRef, isOver } = useDroppable({ id: containerId });
    return (
        <tr ref={setNodeRef}>
            <td colSpan={4}>
                <div
                    className={`flex items-center justify-center rounded py-4 text-xs transition-colors ${
                        isOver ? "bg-amber-900/20 text-amber-400" : "text-brown-600"
                    }`}
                >
                    Drop here
                </div>
            </td>
        </tr>
    );
}

function AssignmentGroupEditor({
    groupId,
    bossId,
    groupName,
    assignments,
    targetMarkers,
    onUpdate,
    onRemove,
    onAdd,
    dragOverInfo,
}) {
    const containerId = `drop-boss-${bossId ?? "event"}-group-${groupId ?? "ungrouped"}`;
    const { setNodeRef: setContainerRef, isOver: isOverContainer } = useDroppable({ id: containerId });

    const sortedAssignments = useMemo(
        () => [...assignments].sort((a, b) => a.sort_order - b.sort_order),
        [assignments],
    );

    const isTargetContainer =
        dragOverInfo !== null &&
        dragOverInfo.destBossId === (bossId ?? null) &&
        dragOverInfo.destGroupId === (groupId ?? null);

    return (
        <div
            ref={setContainerRef}
            className={`overflow-visible rounded-lg border transition-colors ${
                isTargetContainer || isOverContainer ? "border-amber-500 bg-amber-900/10" : "border-brown-700"
            }`}
        >
            {groupName && (
                <div className="border-b border-brown-700 bg-brown-800/60 px-3 py-2">
                    <h3 className="text-sm font-semibold text-amber-400">{groupName}</h3>
                </div>
            )}
            <SortableContext items={sortedAssignments.map((a) => a._key)} strategy={verticalListSortingStrategy}>
                <table className="w-full table-fixed border-collapse">
                    <colgroup>
                        <col className="w-6" />
                        <col />
                        <col />
                        <col className="w-10" />
                    </colgroup>
                    <tbody>
                        {sortedAssignments.length === 0 ? (
                            <EmptyGroupDroppable containerId={containerId} />
                        ) : (
                            sortedAssignments.flatMap((assignment) => {
                                const isCrossContainer = isTargetContainer && dragOverInfo?.overKey !== null;
                                const isOverThis = isCrossContainer && dragOverInfo.overKey === assignment._key;
                                const rows = [];
                                if (isOverThis && dragOverInfo.insertBefore) {
                                    rows.push(<InsertionIndicatorRow key={`${assignment._key}-before`} />);
                                }
                                rows.push(
                                    <AssignmentRowEditor
                                        key={assignment._key}
                                        assignment={assignment}
                                        targetMarkers={targetMarkers}
                                        onUpdate={onUpdate}
                                        onRemove={onRemove}
                                    />,
                                );
                                if (isOverThis && !dragOverInfo.insertBefore) {
                                    rows.push(<InsertionIndicatorRow key={`${assignment._key}-after`} />);
                                }
                                return rows;
                            })
                        )}
                        <AddAssignmentRow onAdd={onAdd} />
                    </tbody>
                </table>
            </SortableContext>
        </div>
    );
}

// ─── Sortable group card ──────────────────────────────────────────────────────

function SortableGroupCard({
    groupKey,
    groupId,
    bossId,
    groupName,
    assignments,
    targetMarkers,
    dragOverInfo,
    onRemoveGroup,
    onRenameGroup,
    onSaveAssignment,
    onDeleteAssignment,
    onCreateAssignment,
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: groupKey,
        data: { type: "group", bossId },
    });

    const style = {
        transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined,
        transition,
        opacity: isDragging ? 0.4 : 1,
        zIndex: isDragging ? 50 : undefined,
    };

    const [editingName, setEditingName] = useState(false);
    const [nameInput, setNameInput] = useState(groupName ?? "");
    const nameRef = useRef(null);

    useEffect(() => {
        if (editingName) nameRef.current?.focus();
    }, [editingName]);

    const commitRename = () => {
        setEditingName(false);
        onRenameGroup(groupKey, nameInput.trim() || null);
    };

    return (
        <div ref={setNodeRef} style={style} className="relative">
            {groupId !== null && (
                <div className="mb-1 flex items-center gap-1">
                    <button
                        type="button"
                        className="cursor-grab touch-none text-brown-600 hover:text-brown-400 active:cursor-grabbing"
                        {...attributes}
                        {...listeners}
                    >
                        <Icon icon="grip-vertical" style="solid" className="text-xs" />
                    </button>

                    {editingName ? (
                        <input
                            ref={nameRef}
                            type="text"
                            value={nameInput}
                            onChange={(e) => setNameInput(e.target.value)}
                            onBlur={commitRename}
                            onKeyDown={(e) => {
                                if (e.key === "Enter") commitRename();
                                if (e.key === "Escape") setEditingName(false);
                            }}
                            className="flex-1 rounded border border-amber-500 bg-brown-800 px-2 py-0.5 text-sm text-amber-400 focus:outline-none"
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                setNameInput(groupName ?? "");
                                setEditingName(true);
                            }}
                            className="flex-1 truncate text-left text-sm font-semibold text-amber-400 hover:text-amber-300"
                        >
                            {groupName ?? <span className="italic text-brown-500">Unnamed group</span>}
                        </button>
                    )}

                    <button
                        type="button"
                        onClick={() => onRemoveGroup(groupKey)}
                        className="ml-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-700/60 text-[9px] text-white hover:bg-red-600"
                    >
                        <Icon icon="times" style="solid" />
                    </button>
                </div>
            )}

            <AssignmentGroupEditor
                groupId={groupId}
                bossId={bossId}
                groupName={null}
                assignments={assignments}
                targetMarkers={targetMarkers}
                dragOverInfo={dragOverInfo}
                onUpdate={onSaveAssignment}
                onRemove={(key) => {
                    const assignment = assignments.find((a) => a._key === key);
                    if (assignment?.id) onDeleteAssignment(assignment.id);
                }}
                onAdd={() => onCreateAssignment(bossId ?? null, groupId ?? null)}
            />
        </div>
    );
}

// ─── Droppable "Add group" button ────────────────────────────────────────────

function DroppableAddGroupButton({ droppableId, onClick, compact = false }) {
    const { setNodeRef, isOver } = useDroppable({ id: droppableId });

    return (
        <div ref={setNodeRef}>
            <button
                type="button"
                onClick={onClick}
                className={`flex w-full items-center justify-center gap-2 rounded border border-dashed text-xs transition-colors ${
                    compact ? "py-2" : "py-6"
                } ${
                    isOver
                        ? "border-amber-500/70 bg-amber-600/15 text-amber-400"
                        : "border-brown-700/50 text-brown-500 hover:border-amber-600/40 hover:bg-amber-600/5 hover:text-amber-500"
                }`}
            >
                <Icon icon="plus" style="solid" className="text-[10px]" />
                {isOver ? "Drop to create new group" : "Add group"}
            </button>
        </div>
    );
}

// ─── Group container (sortable groups + add group button) ─────────────────────

function GroupContainer({
    bossId,
    groups,
    assignments,
    targetMarkers,
    setGroups,
    horizontal = false,
    dragOverInfo,
    onSaveGroup,
    onSaveAssignment,
    onCreateGroup,
    onDeleteGroup,
    onCreateAssignment,
    onDeleteAssignment,
}) {
    const sortedGroups = useMemo(() => [...groups].sort((a, b) => a.sort_order - b.sort_order), [groups]);

    const handleAddGroup = () => {
        const maxOrder = groups.reduce((m, g) => Math.max(m, g.sort_order), -1);
        const newKey = `new-group-${Date.now()}`;
        setGroups((prev) => [
            ...prev,
            {
                _key: newKey,
                group_id: null,
                boss_id: bossId ?? null,
                name: null,
                sort_order: maxOrder + 1,
            },
        ]);
        onCreateGroup(newKey, bossId ?? null, maxOrder + 1);
    };

    const handleRemoveGroup = (groupKey) => {
        const group = groups.find((g) => g._key === groupKey);
        setGroups((prev) => prev.filter((g) => g._key !== groupKey));
        if (group?.group_id) onDeleteGroup(group.group_id);
    };

    const handleRenameGroup = (groupKey, newName) => {
        setGroups((prev) => {
            const updated = prev.map((g) => (g._key === groupKey ? { ...g, name: newName } : g));
            const group = updated.find((g) => g._key === groupKey);
            if (group) onSaveGroup(group);
            return updated;
        });
    };

    const ungroupedAssignments = assignments.filter((a) => a.group_id === null && a.boss_id === (bossId ?? null));

    return (
        <SortableContext items={sortedGroups.map((g) => g._key)} strategy={verticalListSortingStrategy}>
            {horizontal ? (
                <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                    {sortedGroups.map((group) => (
                        <SortableGroupCard
                            key={group._key}
                            groupKey={group._key}
                            groupId={group.group_id}
                            bossId={bossId}
                            groupName={group.name}
                            assignments={assignments.filter(
                                (a) => a.group_id === group.group_id && a.boss_id === (bossId ?? null),
                            )}
                            targetMarkers={targetMarkers}
                            dragOverInfo={dragOverInfo}
                            onRemoveGroup={handleRemoveGroup}
                            onRenameGroup={handleRenameGroup}
                            onSaveAssignment={onSaveAssignment}
                            onCreateAssignment={onCreateAssignment}
                            onDeleteAssignment={onDeleteAssignment}
                        />
                    ))}
                    <div className="lg:mt-7">
                        <AssignmentGroupEditor
                            groupId={null}
                            bossId={bossId}
                            groupName={null}
                            assignments={ungroupedAssignments}
                            targetMarkers={targetMarkers}
                            dragOverInfo={dragOverInfo}
                            onUpdate={onSaveAssignment}
                            onRemove={(key) => {
                                const a = ungroupedAssignments.find((x) => x._key === key);
                                if (a?.id) onDeleteAssignment(a.id);
                            }}
                            onAdd={() => onCreateAssignment(bossId ?? null, null)}
                        />
                    </div>
                    <div className="lg:mt-7">
                        <DroppableAddGroupButton
                            droppableId={`drop-boss-${bossId ?? "event"}-add-new-group`}
                            onClick={handleAddGroup}
                        />
                    </div>
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {sortedGroups.map((group) => (
                        <SortableGroupCard
                            key={group._key}
                            groupKey={group._key}
                            groupId={group.group_id}
                            bossId={bossId}
                            groupName={group.name}
                            assignments={assignments.filter(
                                (a) => a.group_id === group.group_id && a.boss_id === (bossId ?? null),
                            )}
                            targetMarkers={targetMarkers}
                            dragOverInfo={dragOverInfo}
                            onRemoveGroup={handleRemoveGroup}
                            onRenameGroup={handleRenameGroup}
                            onSaveAssignment={onSaveAssignment}
                            onCreateAssignment={onCreateAssignment}
                            onDeleteAssignment={onDeleteAssignment}
                        />
                    ))}
                    <AssignmentGroupEditor
                        groupId={null}
                        bossId={bossId}
                        groupName={null}
                        assignments={ungroupedAssignments}
                        targetMarkers={targetMarkers}
                        dragOverInfo={dragOverInfo}
                        onUpdate={onSaveAssignment}
                        onRemove={(key) => {
                            const a = ungroupedAssignments.find((x) => x._key === key);
                            if (a?.id) onDeleteAssignment(a.id);
                        }}
                        onAdd={() => onCreateAssignment(bossId ?? null, null)}
                    />
                    <DroppableAddGroupButton
                        droppableId={`drop-boss-${bossId ?? "event"}-add-new-group`}
                        onClick={handleAddGroup}
                        compact
                    />
                </div>
            )}
        </SortableContext>
    );
}

// ─── Boss section (assignments only, no strategy) ─────────────────────────────

function BossSection({ boss, raid, commonContainerProps, groupsByBossId, assignments }) {
    return (
        <Collapsible
            key={boss.id}
            title={boss.name}
            sessionKey={`template_boss_expanded_${raid.slug}_${boss.id}`}
            className="border-amber-600/40"
            headerClassName="hover:bg-amber-600/10"
            bodyClassName="border-amber-600/40"
        >
            <div className="flex flex-col gap-3">
                <GroupContainer
                    bossId={boss.id}
                    groups={groupsByBossId[boss.id] ?? []}
                    assignments={assignments}
                    {...commonContainerProps}
                />
            </div>
        </Collapsible>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Edit({ template, targetMarkers, raids }) {
    useEffect(() => {
        resetAssignmentOptionsFetched();
    }, []);

    const { data, setData, patch, processing, errors } = useForm({
        title: template.title,
        raid_ids: template.raids?.map((r) => r.id) ?? [],
    });

    function handleSubmit(e) {
        e.preventDefault();
        patch(route("dashboard.event-templates.update", template.id));
    }

    function toggleRaid(raidId) {
        setData(
            "raid_ids",
            data.raid_ids.includes(raidId) ? data.raid_ids.filter((id) => id !== raidId) : [...data.raid_ids, raidId],
        );
    }

    const flattenAssignments = useCallback((templateData) => {
        const rows = [];
        let key = 0;

        const makeRow = ({ a, bossId, groupId }) => ({
            _key: `existing-${a.id ?? key++}`,
            id: a.id ?? null,
            _leftSide: a.left ?? null,
            _rightSide: a.right ?? null,
            event_id: templateData.id,
            boss_id: bossId ?? null,
            group_id: groupId ?? null,
            sort_order: a.sort_order,
            left_type: storageFromSide(a.left).left_type,
            left_value: storageFromSide(a.left).left_value,
            right_type: storageFromSide(a.right).left_type,
            right_value: storageFromSide(a.right).left_value,
        });

        (templateData.assignments?.groups ?? []).forEach((g) =>
            (g.assignments ?? []).forEach((a) => rows.push(makeRow({ a, bossId: null, groupId: g.id }))),
        );

        (templateData.assignments?.ungrouped ?? []).forEach((a) =>
            rows.push(makeRow({ a, bossId: null, groupId: null })),
        );

        (templateData.raids ?? []).forEach((raid) =>
            (raid.bosses ?? []).forEach((boss) => {
                (boss.assignments?.groups ?? []).forEach((g) =>
                    (g.assignments ?? []).forEach((a) => rows.push(makeRow({ a, bossId: boss.id, groupId: g.id }))),
                );
                (boss.assignments?.ungrouped ?? []).forEach((a) =>
                    rows.push(makeRow({ a, bossId: boss.id, groupId: null })),
                );
            }),
        );

        return rows;
    }, []);

    const flattenGroups = useCallback((templateData) => {
        const result = [];
        let sortOrder = 0;

        (templateData.assignments?.groups ?? []).forEach((g) => {
            result.push({
                _key: `existing-group-${g.id}`,
                group_id: g.id,
                boss_id: null,
                name: g.name ?? null,
                sort_order: sortOrder++,
            });
        });

        (templateData.raids ?? []).forEach((raid) =>
            (raid.bosses ?? []).forEach((boss) => {
                (boss.assignments?.groups ?? []).forEach((g) => {
                    result.push({
                        _key: `existing-group-${g.id}`,
                        group_id: g.id,
                        boss_id: boss.id,
                        name: g.name ?? null,
                        sort_order: sortOrder++,
                    });
                });
            }),
        );

        return result;
    }, []);

    const [assignments, setAssignments] = useState(() => flattenAssignments(template));
    const [groups, setGroups] = useState(() => flattenGroups(template));
    const [saving, setSaving] = useState(0);
    const [draggingKey, setDraggingKey] = useState(null);

    const beginSave = useCallback(() => setSaving((n) => n + 1), []);
    const endSave = useCallback(() => setSaving((n) => Math.max(0, n - 1)), []);

    const saveGroup = useCallback(
        (group) => {
            if (!group.group_id) return;
            beginSave();
            window.axios
                .patch(route("api.events.groups.update", [template.id, group.group_id]), {
                    name: group.name,
                    sort_order: group.sort_order,
                })
                .finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const saveGroupReorder = useCallback(
        (orderedGroups) => {
            const ids = orderedGroups.filter((g) => g.group_id !== null).map((g) => g.group_id);
            if (ids.length === 0) return;
            beginSave();
            window.axios.patch(route("api.events.groups.reorder", template.id), { order: ids }).finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const saveAssignment = useCallback(
        (assignment) => {
            if (!assignment.id) return;

            setAssignments((prev) => prev.map((a) => (a.id === assignment.id ? { ...a, ...assignment } : a)));
            beginSave();

            const body = {
                boss_id: assignment.boss_id,
                group_id: assignment.group_id,
            };

            if (!assignment._changedSide || assignment._changedSide === "left") {
                body.left_type = assignment.left_type;
                body.left_value = assignment.left_value;
            }
            if (!assignment._changedSide || assignment._changedSide === "right") {
                body.right_type = assignment.right_type;
                body.right_value = assignment.right_value;
            }

            window.axios
                .patch(route("api.events.assignments.update", [template.id, assignment.id]), body)
                .finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const saveAssignmentReorder = useCallback(
        (orderedAssignments) => {
            const ids = orderedAssignments.filter((a) => a.id).map((a) => a.id);
            if (ids.length === 0) return;
            beginSave();
            window.axios.patch(route("api.events.assignments.reorder", template.id), { order: ids }).finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const createGroup = useCallback(
        (key, bossId, sortOrder) => {
            beginSave();
            window.axios
                .post(route("api.events.groups.store", template.id), { boss_id: bossId, sort_order: sortOrder })
                .then((res) =>
                    setGroups((prev) =>
                        prev.map((g) =>
                            g._key === key
                                ? { ...g, group_id: res.data.id, name: res.data.name, sort_order: res.data.sort_order }
                                : g,
                        ),
                    ),
                )
                .finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const deleteGroup = useCallback(
        (groupId) => {
            beginSave();
            window.axios.delete(route("api.events.groups.destroy", [template.id, groupId])).finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const createAssignment = useCallback(
        (bossId, groupId) => {
            beginSave();
            window.axios
                .post(route("api.events.assignments.store", template.id), { boss_id: bossId, group_id: groupId })
                .then((res) =>
                    setAssignments((prev) => [
                        ...prev,
                        {
                            _key: `existing-${res.data.id}`,
                            id: res.data.id,
                            _leftSide: null,
                            _rightSide: null,
                            event_id: template.id,
                            boss_id: bossId ?? null,
                            group_id: groupId ?? null,
                            sort_order: res.data.sort_order,
                            left_type: null,
                            left_value: null,
                            right_type: null,
                            right_value: null,
                        },
                    ]),
                )
                .finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const deleteAssignment = useCallback(
        (assignmentId) => {
            setAssignments((prev) => prev.filter((a) => a.id !== assignmentId));
            beginSave();
            window.axios.delete(route("api.events.assignments.destroy", [template.id, assignmentId])).finally(endSave);
        },
        [template.id, beginSave, endSave],
    );

    const assignmentSensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor),
    );
    const [activeAssignment, setActiveAssignment] = useState(null);
    const [dragOverInfo, setDragOverInfo] = useState(null);

    const parseContainerId = (id) => {
        const m = String(id).match(/^drop-boss-(.+)-group-(.+)$/);
        if (!m) return null;
        return {
            boss_id: m[1] === "event" ? null : Number(m[1]),
            group_id: m[2] === "ungrouped" ? null : Number(m[2]),
        };
    };

    const handleDragOver = useCallback(
        ({ active, over }) => {
            if (!active.data.current || active.data.current.type === "group") {
                setDragOverInfo(null);
                return;
            }
            if (!over) return;

            const addNewGroupTarget = parseAddNewGroupId(over.id);
            if (addNewGroupTarget) {
                setDragOverInfo({
                    overKey: null,
                    insertBefore: true,
                    destBossId: addNewGroupTarget.boss_id,
                    destGroupId: null,
                    destIsNewGroup: true,
                });
                return;
            }

            const activeData = active.data.current;
            let destBossId, destGroupId, overKey;

            const containerFromOver = parseContainerId(over.id);
            if (containerFromOver) {
                destBossId = containerFromOver.boss_id;
                destGroupId = containerFromOver.group_id;
                setDragOverInfo((prev) => {
                    const isSame = activeData.boss_id === destBossId && activeData.group_id === destGroupId;
                    if (isSame) return null;
                    if (
                        prev &&
                        prev.destBossId === destBossId &&
                        prev.destGroupId === destGroupId &&
                        prev.overKey !== null
                    ) {
                        return prev;
                    }
                    return { overKey: null, insertBefore: true, destBossId, destGroupId };
                });
                return;
            }

            const overAssignment = assignments.find((a) => a._key === over.id);
            if (!overAssignment) return;
            destBossId = overAssignment.boss_id;
            destGroupId = overAssignment.group_id;
            overKey = overAssignment._key;

            const isSameContainer = activeData.boss_id === destBossId && activeData.group_id === destGroupId;
            if (isSameContainer) {
                setDragOverInfo(null);
                return;
            }

            let insertBefore = true;
            if (over.rect) {
                const midY = over.rect.top + over.rect.height / 2;
                const translated = active.rect.current.translated;
                const activeCenter = translated ? translated.top + translated.height / 2 : midY;
                insertBefore = activeCenter < midY;
            }

            setDragOverInfo({ overKey, insertBefore, destBossId, destGroupId });
        },
        [assignments],
    );

    const handleDragEnd = useCallback(
        ({ active, over }) => {
            setActiveAssignment(null);
            setDragOverInfo(null);
            if (!over || active.id === over.id) return;

            const activeData = active.data.current;

            if (activeData?.type === "group") {
                const bossId = activeData.bossId ?? null;
                const scopedGroups = groups
                    .filter((g) => g.boss_id === bossId)
                    .sort((a, b) => a.sort_order - b.sort_order);

                const oldIdx = scopedGroups.findIndex((g) => g._key === active.id);
                const newIdx = scopedGroups.findIndex((g) => g._key === over.id);
                if (oldIdx === -1 || newIdx === -1) return;

                const reordered = [...scopedGroups];
                const [moved] = reordered.splice(oldIdx, 1);
                reordered.splice(newIdx, 0, moved);

                const updated = reordered.map((g, i) => ({ ...g, sort_order: i }));
                setGroups((prev) => prev.map((g) => updated.find((u) => u._key === g._key) ?? g));
                saveGroupReorder(updated);
                return;
            }

            const activeKey = active.id;

            const addNewGroupFromOver = parseAddNewGroupId(over.id);
            const dropIsNewGroup = addNewGroupFromOver !== null || dragOverInfo?.destIsNewGroup === true;

            if (dropIsNewGroup) {
                const targetBossId = addNewGroupFromOver?.boss_id ?? dragOverInfo?.destBossId ?? null;
                const movedAssignment = assignments.find((a) => a._key === activeKey);
                if (!movedAssignment) return;

                const sourceGroupId = activeData?.group_id ?? null;
                if (sourceGroupId !== null) {
                    const remainingInSource = assignments.filter(
                        (a) => a.group_id === sourceGroupId && a._key !== activeKey,
                    );
                    if (remainingInSource.length === 0) {
                        setGroups((prev) => prev.filter((g) => g.group_id !== sourceGroupId));
                        deleteGroup(sourceGroupId);
                    }
                }

                const maxGroupOrder = groups
                    .filter((g) => g.boss_id === targetBossId)
                    .reduce((m, g) => Math.max(m, g.sort_order), -1);
                const newGroupSortOrder = maxGroupOrder + 1;
                const tempGroupKey = `new-group-${Date.now()}`;

                setGroups((prev) => [
                    ...prev,
                    {
                        _key: tempGroupKey,
                        group_id: null,
                        boss_id: targetBossId,
                        name: "New group",
                        sort_order: newGroupSortOrder,
                    },
                ]);

                beginSave();
                window.axios
                    .post(route("api.events.groups.store", template.id), { sort_order: newGroupSortOrder })
                    .then((res) => {
                        const groupData = res.data;
                        if (!groupData) return;

                        setGroups((prev) =>
                            prev.map((g) =>
                                g._key === tempGroupKey
                                    ? {
                                          ...g,
                                          group_id: groupData.id,
                                          name: groupData.name,
                                          sort_order: groupData.sort_order,
                                      }
                                    : g,
                            ),
                        );

                        setAssignments((prev) =>
                            prev.map((a) =>
                                a._key === activeKey
                                    ? { ...a, boss_id: targetBossId, group_id: groupData.id, sort_order: 0 }
                                    : a,
                            ),
                        );

                        if (movedAssignment.id) {
                            return window.axios.patch(
                                route("api.events.assignments.update", [template.id, movedAssignment.id]),
                                {
                                    left_type: movedAssignment.left_type,
                                    left_value: movedAssignment.left_value,
                                    right_type: movedAssignment.right_type,
                                    right_value: movedAssignment.right_value,
                                    boss_id: targetBossId,
                                    group_id: groupData.id,
                                    sort_order: 0,
                                },
                            );
                        }
                    })
                    .finally(endSave);

                return;
            }

            let destBossId, destGroupId;
            const containerFromOver = parseContainerId(over.id);
            if (containerFromOver) {
                destBossId = containerFromOver.boss_id;
                destGroupId = containerFromOver.group_id;
            } else {
                const overAssignment = assignments.find((a) => a._key === over.id);
                if (!overAssignment) return;
                destBossId = overAssignment.boss_id;
                destGroupId = overAssignment.group_id;
            }

            const isSameContainer = activeData?.boss_id === destBossId && activeData?.group_id === destGroupId;

            if (isSameContainer) {
                const containerAssignments = assignments
                    .filter((a) => a.boss_id === destBossId && a.group_id === destGroupId)
                    .sort((a, b) => a.sort_order - b.sort_order);

                const oldIndex = containerAssignments.findIndex((a) => a._key === activeKey);
                const newIndex = containerAssignments.findIndex((a) => a._key === over.id);
                if (oldIndex === -1 || newIndex === -1 || oldIndex === newIndex) return;

                const reordered = [...containerAssignments];
                const [moved] = reordered.splice(oldIndex, 1);
                reordered.splice(newIndex, 0, moved);
                const updated = reordered.map((a, i) => ({ ...a, sort_order: i }));

                setAssignments((prev) => prev.map((a) => updated.find((u) => u._key === a._key) ?? a));
                saveAssignmentReorder(updated);
            } else {
                const destAssignments = assignments
                    .filter((a) => a.boss_id === destBossId && a.group_id === destGroupId)
                    .sort((a, b) => a.sort_order - b.sort_order);

                let insertAtIndex = destAssignments.length;
                if (dragOverInfo?.overKey) {
                    const overIdx = destAssignments.findIndex((a) => a._key === dragOverInfo.overKey);
                    if (overIdx !== -1) {
                        insertAtIndex = dragOverInfo.insertBefore ? overIdx : overIdx + 1;
                    }
                }

                const reordered = [...destAssignments];
                const movedAssignment = assignments.find((a) => a._key === activeKey);
                if (!movedAssignment) return;

                reordered.splice(insertAtIndex, 0, { ...movedAssignment, boss_id: destBossId, group_id: destGroupId });
                const updated = reordered.map((a, i) => ({ ...a, sort_order: i }));

                setAssignments((prev) =>
                    prev.map((a) => {
                        const u = updated.find((x) => x._key === a._key);
                        return u ?? a;
                    }),
                );

                if (movedAssignment.id) {
                    beginSave();
                    window.axios
                        .patch(route("api.events.assignments.update", [template.id, movedAssignment.id]), {
                            left_type: movedAssignment.left_type,
                            left_value: movedAssignment.left_value,
                            right_type: movedAssignment.right_type,
                            right_value: movedAssignment.right_value,
                            boss_id: destBossId,
                            group_id: destGroupId,
                            sort_order: insertAtIndex,
                        })
                        .then(() => {
                            const finalOrder = updated.filter((a) => a.id);
                            if (finalOrder.length > 1) {
                                saveAssignmentReorder(finalOrder);
                            }
                        })
                        .finally(endSave);
                }

                const sourceGroupId = activeData?.group_id ?? null;
                if (sourceGroupId !== null) {
                    const remainingInSource = assignments.filter(
                        (a) => a.group_id === sourceGroupId && a._key !== activeKey,
                    );
                    if (remainingInSource.length === 0) {
                        setGroups((prev) => prev.filter((g) => g.group_id !== sourceGroupId));
                        deleteGroup(sourceGroupId);
                    }
                }
            }
        },
        [
            assignments,
            dragOverInfo,
            groups,
            template.id,
            beginSave,
            endSave,
            saveAssignmentReorder,
            saveGroupReorder,
            setAssignments,
            setGroups,
            deleteGroup,
        ],
    );

    const generalGroups = useMemo(
        () => groups.filter((g) => g.boss_id === null).sort((a, b) => a.sort_order - b.sort_order),
        [groups],
    );

    const groupsByBossId = useMemo(() => {
        const map = {};
        groups
            .filter((g) => g.boss_id !== null)
            .forEach((g) => {
                if (!map[g.boss_id]) map[g.boss_id] = [];
                map[g.boss_id].push(g);
            });
        Object.values(map).forEach((arr) => arr.sort((a, b) => a.sort_order - b.sort_order));
        return map;
    }, [groups]);

    const commonContainerProps = {
        targetMarkers,
        setGroups,
        dragOverInfo,
        onSaveGroup: saveGroup,
        onSaveAssignment: saveAssignment,
        onCreateGroup: createGroup,
        onDeleteGroup: deleteGroup,
        onCreateAssignment: createAssignment,
        onDeleteAssignment: deleteAssignment,
    };

    return (
        <Master title={`Editing template: ${template.title}`}>
            <SharedHeader title={template.title} backgroundClass={template.background ?? "bg-ssctk"} />
            <ToolNav>
                <div className="flex-initial space-x-4">
                    <Link
                        href={route("dashboard.event-templates.index")}
                        className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to templates
                    </Link>
                </div>
                <div className="flex items-center space-x-4">
                    <AutoSaveLabel saving={saving > 0} />
                </div>
            </ToolNav>

            <div className="py-8 text-white">
                <div className="container mx-auto px-4">
                    {/* Template metadata */}
                    <form onSubmit={handleSubmit} className="mb-8">
                        <MetaCard>
                            <MetaItem icon="tag">
                                <Tooltip text="Click to edit">
                                    <label className="sr-only" htmlFor="template-title">
                                        Template name
                                    </label>
                                    <input
                                        type="text"
                                        value={data.title}
                                        onChange={(e) => setData("title", e.target.value)}
                                        className="rounded border border-transparent bg-transparent px-1 text-white focus:border-amber-500 focus:outline-none"
                                        placeholder="Template name"
                                    />
                                </Tooltip>
                                {errors.title && <span className="ml-2 text-xs text-red-400">{errors.title}</span>}
                            </MetaItem>
                            {raids && raids.length > 0 && (
                                <MetaItem icon="shield-alt">
                                    <div className="w-64">
                                        <label className="sr-only" htmlFor="raid-ids">
                                            Raids included in this template
                                        </label>
                                        <FilterDropdown
                                            label={{ singular: "raid", plural: "raids" }}
                                            options={raids}
                                            selected={data.raid_ids}
                                            onChange={(ids) => setData("raid_ids", ids)}
                                        />
                                    </div>
                                    {errors.raid_ids && (
                                        <span className="ml-2 text-xs text-red-400">{errors.raid_ids}</span>
                                    )}
                                </MetaItem>
                            )}
                            <div className="grow" />
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-amber-600 px-4 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-amber-500 disabled:opacity-50"
                            >
                                {processing ? "Saving…" : "Save"}
                            </button>
                        </MetaCard>
                    </form>

                    <DndContext
                        sensors={assignmentSensors}
                        collisionDetection={closestCenter}
                        onDragStart={(e) => {
                            const a = assignments.find((x) => x._key === e.active.id);
                            setActiveAssignment(a ?? null);
                            setDraggingKey(a?._key ?? null);
                        }}
                        onDragOver={handleDragOver}
                        onDragEnd={(e) => {
                            setDraggingKey(null);
                            handleDragEnd(e);
                        }}
                        onDragCancel={() => {
                            setActiveAssignment(null);
                            setDragOverInfo(null);
                            setDraggingKey(null);
                        }}
                    >
                        {/* General Assignments */}
                        <section className="mb-8">
                            <h2 className="mb-3 text-base font-semibold uppercase tracking-wider text-amber-500/80">
                                General Assignments
                            </h2>
                            <GroupContainer
                                bossId={null}
                                groups={generalGroups}
                                assignments={assignments}
                                horizontal
                                {...commonContainerProps}
                            />
                        </section>

                        {/* Boss assignments */}
                        {template.raids?.length > 0 && (
                            <div className="space-y-6">
                                {template.raids.map((raid) => (
                                    <div key={raid.slug}>
                                        {template.raids.length > 1 && (
                                            <h2 className="mb-3 text-base font-semibold uppercase tracking-wider text-amber-500/80">
                                                {raid.name}
                                            </h2>
                                        )}
                                        <div className="flex flex-col gap-2">
                                            {(raid.bosses ?? []).map((boss) => (
                                                <BossSection
                                                    key={boss.id}
                                                    boss={boss}
                                                    raid={raid}
                                                    commonContainerProps={commonContainerProps}
                                                    groupsByBossId={groupsByBossId}
                                                    assignments={assignments}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        <DragOverlay>
                            {activeAssignment ? (
                                <table className="w-full table-fixed rounded border border-amber-500/50 bg-brown-800 opacity-90 shadow-xl">
                                    <tbody>
                                        <tr className="border-b border-brown-700/50">
                                            <td className="w-6 px-1 py-2 text-brown-700">
                                                <Icon icon="grip-vertical" style="solid" className="text-xs" />
                                            </td>
                                            <td className="w-1/2 border-r border-brown-700/50 px-3 py-2.5 text-sm text-brown-200">
                                                {labelFromSide(activeAssignment._leftSide).label || (
                                                    <span className="italic text-brown-600">empty</span>
                                                )}
                                            </td>
                                            <td className="w-1/2 px-3 py-2.5 text-sm text-brown-200">
                                                {labelFromSide(activeAssignment._rightSide).label || (
                                                    <span className="italic text-brown-600">empty</span>
                                                )}
                                            </td>
                                            <td className="w-10" />
                                        </tr>
                                    </tbody>
                                </table>
                            ) : null}
                        </DragOverlay>
                    </DndContext>
                </div>
            </div>
        </Master>
    );
}
