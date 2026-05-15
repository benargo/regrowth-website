import { Link } from "@inertiajs/react";

function pageFromUrl(url) {
    try {
        return Number(new URL(url).searchParams.get("page") ?? 1);
    } catch {
        return 1;
    }
}

export default function Pagination({ links, meta, itemName = "items", className = "", onPageChange = null }) {
    if (!links || meta.last_page <= 1) {
        return null;
    }

    return (
        <nav className={`mt-6 flex flex-col items-center justify-between gap-4 ${className}`}>
            <div className="text-sm text-gray-400">
                Showing {meta.from} to {meta.to} of {meta.total} {itemName}
            </div>
            <div className="flex gap-1">
                {links.map((link, index) => {
                    if (!link.url) {
                        return (
                            <span
                                key={index}
                                className="rounded bg-gray-800 px-3 py-1 text-sm text-gray-500"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    }

                    if (onPageChange) {
                        return (
                            <button
                                key={index}
                                type="button"
                                onClick={() => onPageChange(pageFromUrl(link.url))}
                                className={`rounded px-3 py-1 text-sm transition-colors ${
                                    link.active
                                        ? "bg-amber-600 text-white"
                                        : "bg-gray-700 text-gray-300 hover:bg-gray-600"
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    }

                    return (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={`rounded px-3 py-1 text-sm transition-colors ${
                                link.active ? "bg-amber-600 text-white" : "bg-gray-700 text-gray-300 hover:bg-gray-600"
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </nav>
    );
}
