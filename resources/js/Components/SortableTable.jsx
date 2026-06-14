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
        <div
            role="columnheader"
            className="table-cell cursor-pointer select-none px-4 py-3 text-left text-sm font-semibold text-amber-500 transition-colors hover:text-amber-400"
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
        </div>
    );
}

export default function SortableTable({
    columns,
    sortColumn,
    sortDirection,
    onSort,
    className,
    children,
}) {
    if (process.env.NODE_ENV !== "production" && !columns) {
        throw new Error("SortableTable: a 'columns' prop is required.");
    }

    const handleSort = (column) => {
        const newDirection =
            sortColumn === column ? (sortDirection === "asc" ? "desc" : "asc") : "asc";
        onSort(column, newDirection);
    };

    return (
        <div role="table" className={`table w-full text-left${className ? ` ${className}` : ""}`}>
            <div role="rowgroup" className="table-header-group">
                <div role="row" className="table-row border-b border-brown-700">
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
                </div>
            </div>
            <div role="rowgroup" className="table-row-group">
                {children}
            </div>
        </div>
    );
}
