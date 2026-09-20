import { Link } from "@inertiajs/react";

export default function ToolNav({ children }) {
    return (
        <nav className="bg-ground-900 shadow">
            <div className="container mx-auto px-4">
                <div className="flex min-h-12 flex-row flex-wrap items-center justify-between gap-x-2">{children}</div>
            </div>
        </nav>
    );
}

export function ToolNavLink({ as: Component = Link, className = "", children, ...props }) {
    return (
        <Component
            className={`my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-ground-800 active:border-primary ${className}`}
            {...props}
        >
            {children}
        </Component>
    );
}
