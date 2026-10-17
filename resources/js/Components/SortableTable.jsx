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
            aria-sort={isActive ? (sortDirection === "asc" ? "ascending" : "descending") : "none"}
            className="table-cell px-4 py-3 text-left text-sm font-semibold"
        >
            <button
                type="button"
                className="inline-flex w-full cursor-pointer items-center gap-2 rounded-sm text-left text-amber-500 transition-colors select-none hover:text-amber-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                onClick={() => onSort(column)}
            >
                {label}
                <span className="text-xs" aria-hidden="true">
                    {isActive ? (
                        sortDirection === "asc" ? (
                            <Icon icon="sort-up" style="solid" />
                        ) : (
                            <Icon icon="sort-down" style="solid" />
                        )
                    ) : (
                        <Icon icon="sort" style="solid" className="text-gray-500" />
                    )}
                </span>
            </button>
        </div>
    );
}

export default function SortableTable({ columns, sortColumn, sortDirection, onSort, className, children }) {
    if (process.env.NODE_ENV !== "production" && !columns) {
        throw new Error("SortableTable: a 'columns' prop is required.");
    }

    const handleSort = (column) => {
        const newDirection = sortColumn === column ? (sortDirection === "asc" ? "desc" : "asc") : "asc";
        onSort(column, newDirection);
    };

    return (
        <div role="table" className={`table w-full text-left${className ? ` ${className}` : ""}`}>
            <div role="rowgroup" className="table-header-group">
                <div role="row" className="table-row border-b border-amber-600">
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
