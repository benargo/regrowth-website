import cn from "@/Helpers/cn";

/**
 * Thin, styled wrappers over native table elements. Each one merges its
 * `className` over the base classes and passes any other props through.
 * Cells and headers carry no alignment, so add `text-left`, `text-right`
 * or `text-center` where they're used.
 */
export function Table({ className = "", children, ...props }) {
    return (
        <div className="overflow-x-auto">
            <table {...props} className={cn("w-full border-collapse", className)}>
                {children}
            </table>
        </div>
    );
}

export function TableHead({ className = "border-amber-600", ...props }) {
    return <thead {...props} className={`border-b ${className}`} />;
}

export function TableBody({ className = "", ...props }) {
    return <tbody {...props} className={cn("divide-y divide-amber-600", className)} />;
}

export function TableRow({ className = "", ...props }) {
    return <tr {...props} className={cn("hover:bg-brown-800/50 transition-colors", className)} />;
}

export function TableHeader({ className = "", ...props }) {
    return <th {...props} className={cn("px-4 py-3 text-sm font-semibold text-amber-500", className)} />;
}

export function TableCell({ className = "", ...props }) {
    return <td {...props} className={cn("px-4 py-3", className)} />;
}
