import { useState, useEffect, Children, isValidElement } from "react";
import Icon from "@/Components/FontAwesome/Icon";

function columnLabel(key) {
    return key
        .split("_")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

function SortableHeader({ column, label, sortColumn, sortDirection, onSort }) {
    const isActive = sortColumn === column;

    return (
        <th
            className="cursor-pointer select-none px-4 py-3 text-left text-sm font-semibold text-amber-500 transition-colors hover:text-amber-400"
            onClick={() => onSort(column)}
        >
            <span className="inline-flex items-center gap-2">
                {label}
                <span className="text-xs">
                    {isActive ? (
                        sortDirection === "asc" ? (
                            <Icon icon="sort-up" style="solid" />
                        ) : (
                            <Icon icon="sort-down" style="solid" />
                        )
                    ) : (
                        <Icon icon="sort" style="solid" className="text-gray-600" />
                    )}
                </span>
            </span>
        </th>
    );
}

export default function SortableTable({
    columns,
    onSort,
    defaultSortColumn,
    defaultSortDirection = "asc",
    className,
    children,
}) {
    const childArray = Children.toArray(children);
    const hasCustomThead = childArray.some(
        (child) => isValidElement(child) && child.type === "thead",
    );
    const hasCustomTbody = childArray.some(
        (child) => isValidElement(child) && child.type === "tbody",
    );

    if (process.env.NODE_ENV !== "production" && !hasCustomThead && !columns) {
        throw new Error("SortableTable: either a 'columns' prop or a <thead> child is required.");
    }

    const initialColumn = defaultSortColumn ?? (columns?.[0] ?? "");
    const [sortColumn, setSortColumn] = useState(initialColumn);
    const [sortDirection, setSortDirection] = useState(defaultSortDirection);

    const handleSort = (column) => {
        const newDirection = sortColumn === column ? (sortDirection === "asc" ? "desc" : "asc") : "asc";
        setSortColumn(column);
        setSortDirection(newDirection);
    };

    useEffect(() => {
        if (onSort) {
            onSort(sortColumn, sortDirection);
        }
    }, [sortColumn, sortDirection]);

    const bodyChildren = hasCustomTbody
        ? childArray.filter((child) => isValidElement(child) && child.type === "tbody")
        : null;

    const rowChildren = !hasCustomTbody
        ? childArray.filter((child) => !isValidElement(child) || child.type !== "thead")
        : null;

    return (
        <table className={`w-full text-left${className ? ` ${className}` : ""}`}>
            {hasCustomThead
                ? childArray.filter((child) => isValidElement(child) && child.type === "thead")
                : columns && (
                      <thead>
                          <tr className="border-b border-brown-700">
                              {columns.map((col) => (
                                  <SortableHeader
                                      key={col}
                                      column={col}
                                      label={columnLabel(col)}
                                      sortColumn={sortColumn}
                                      sortDirection={sortDirection}
                                      onSort={handleSort}
                                  />
                              ))}
                          </tr>
                      </thead>
                  )}
            {hasCustomTbody ? (
                bodyChildren
            ) : (
                <tbody className="divide-y divide-brown-700/50">{rowChildren}</tbody>
            )}
        </table>
    );
}
