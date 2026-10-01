import { useEffect, useRef } from "react";
import Checkbox from "@/Components/Checkbox";
import Pill from "@/Components/Pill";

/**
 * Split options into [label, options] groups, keeping first-seen order.
 */
function groupOptions(options, groupBy) {
    if (!groupBy) {
        return [[null, options]];
    }

    const groups = new Map();

    options.forEach((option) => {
        const key = groupBy(option);
        groups.set(key, [...(groups.get(key) ?? []), option]);
    });

    return [...groups.entries()];
}

/**
 * A fieldset of checkboxes for linking records to a dataset record. When
 * `ownerOf` names another record that owns an option, the option stays
 * selectable, with a pill naming that owner inside its label, because
 * ticking it moves the option here.
 */
export default function RecordChecklist({
    legend,
    hideLegend = false,
    name,
    options,
    selectedIds,
    onChange,
    ownerOf = () => null,
    renderLabel = (option) => option.name,
    groupBy = null,
    emptyMessage,
    error,
    selectAll = false,
}) {
    const errorId = `${name}-error`;
    const selectAllId = `${name}-select-all`;
    const selectAllRef = useRef(null);
    const optionIds = options.map((option) => option.id);
    const selectedCount = optionIds.filter((id) => selectedIds.includes(id)).length;
    const allSelected = optionIds.length > 0 && selectedCount === optionIds.length;
    const someSelected = selectedCount > 0 && !allSelected;

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someSelected;
        }
    }, [someSelected]);

    function toggle(id) {
        onChange(selectedIds.includes(id) ? selectedIds.filter((selected) => selected !== id) : [...selectedIds, id]);
    }

    function toggleAll() {
        onChange(
            allSelected
                ? selectedIds.filter((id) => !optionIds.includes(id))
                : [...new Set([...selectedIds, ...optionIds])],
        );
    }

    return (
        <fieldset className="flex flex-col gap-4" aria-describedby={error ? errorId : undefined}>
            <legend className={hideLegend ? "sr-only" : "mb-3 font-serif text-lg text-white"}>{legend}</legend>

            {selectAll && options.length > 0 && (
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <label
                        htmlFor={selectAllId}
                        className="text-secondary-100 flex min-h-8 cursor-pointer items-center gap-3 border border-transparent px-3 hover:text-white"
                    >
                        <Checkbox
                            ref={selectAllRef}
                            id={selectAllId}
                            checked={allSelected}
                            onChange={toggleAll}
                            aria-describedby={`${selectAllId}-count`}
                        />
                        Select all
                    </label>
                    <span id={`${selectAllId}-count`} className="text-secondary-300 text-sm">
                        {selectedCount} of {optionIds.length} selected
                    </span>
                </div>
            )}

            {options.length === 0 ? (
                <p className="text-secondary-300 text-sm">{emptyMessage}</p>
            ) : (
                groupOptions(options, groupBy).map(([group, members]) => (
                    <div key={group ?? "all"} className="flex flex-col gap-2">
                        {group && <h3 className="text-secondary-200 text-sm font-semibold">{group}</h3>}
                        <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {members.map((option) => {
                                const inputId = `${name}-${option.id}`;
                                const owner = ownerOf(option);

                                return (
                                    <li key={option.id}>
                                        <label
                                            htmlFor={inputId}
                                            className="border-ink-600/40 has-checked:border-ink-400 has-checked:bg-ink-800/40 hover:bg-ink-600/10 flex h-full cursor-pointer items-start gap-3 rounded border px-3 py-2"
                                        >
                                            <Checkbox
                                                id={inputId}
                                                checked={selectedIds.includes(option.id)}
                                                onChange={() => toggle(option.id)}
                                                className="mt-1"
                                            />
                                            <span className="flex min-w-0 flex-col gap-1">
                                                <span className="text-white">{renderLabel(option)}</span>
                                                {owner && (
                                                    <span>
                                                        <Pill
                                                            bgColor="bg-ground-800"
                                                            textColor="text-secondary-200"
                                                            borderColor="border-ink-600"
                                                        >
                                                            In {owner}
                                                        </Pill>
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))
            )}

            {error && (
                <p id={errorId} className="text-sm text-red-300">
                    {error}
                </p>
            )}
        </fieldset>
    );
}
