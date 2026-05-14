import { useCallback, useEffect, useRef, useState } from "react";
import Icon from "@/Components/FontAwesome/Icon";
import Pagination from "@/Components/Pagination";
import Tooltip from "@/Components/Tooltip";

function BlizzardIconPickerSkeleton() {
    return (
        <div className="flex items-center justify-center py-12 text-brown-400">
            <Icon icon="spinner" style="solid" className="fa-spin" />
        </div>
    );
}

export default function BlizzardIconPicker({ onSelect, maxSelections = 1 }) {
    const [query, setQuery] = useState("");
    const [paginatorLinks, setPaginatorLinks] = useState(null);
    const [paginatorMeta, setPaginatorMeta] = useState({ last_page: 1 });
    const [icons, setIcons] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState([]);
    const debounceRef = useRef(null);

    const fetchIcons = useCallback((q, p) => {
        setLoading(true);
        const params = new URLSearchParams({ page: p });
        if (q) params.set("name", q);

        fetch(`${route("api.blizzard.media")}?${params}`, {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        })
            .then((r) => r.json())
            .then((data) => {
                setIcons(data.data ?? []);
                setPaginatorLinks(data.links ?? null);
                setPaginatorMeta({
                    current_page: data.current_page,
                    last_page: data.last_page ?? 1,
                    from: data.from,
                    to: data.to,
                    total: data.total,
                });
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, []);

    useEffect(() => {
        fetchIcons("", 1);
    }, []);

    const handleQueryChange = (e) => {
        const val = e.target.value;
        setQuery(val);
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => fetchIcons(val, 1), 300);
    };

    const handlePage = (p) => {
        fetchIcons(query, p);
    };

    const toggleIcon = (icon) => {
        setSelected((prev) => {
            const alreadySelected = prev.some((s) => s.id === icon.id);
            if (alreadySelected) {
                return prev.filter((s) => s.id !== icon.id);
            }
            if (maxSelections === 1) {
                return [icon];
            }
            if (prev.length >= maxSelections) {
                return prev;
            }
            return [...prev, icon];
        });
    };

    const handleConfirm = () => {
        if (selected.length === 0) return;
        if (maxSelections === 1) {
            onSelect(selected[0].url);
        } else {
            onSelect(selected.map((s) => s.url));
        }
    };

    const selectionCount = selected.length;
    const atLimit = selectionCount >= maxSelections;

    return (
        <div className="flex flex-col">
            {/* Search bar */}
            <div className="px-1 pb-3">
                <div className="relative">
                    <Icon
                        icon="search"
                        style="solid"
                        className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-brown-500"
                    />
                    <input
                        type="text"
                        value={query}
                        onChange={handleQueryChange}
                        placeholder="Search icons…"
                        className="w-full rounded border border-brown-600 bg-brown-800 py-2 pl-8 pr-3 text-sm text-white placeholder-brown-500 focus:border-amber-500 focus:outline-none"
                        autoFocus
                    />
                </div>
            </div>

            {/* Icon grid */}
            <div className="min-h-[160px]">
                {loading ? (
                    <BlizzardIconPickerSkeleton />
                ) : (
                    <div className="grid grid-cols-10 place-items-center gap-1">
                        {icons.map((icon) => {
                            const isSelected = selected.some((s) => s.id === icon.id);
                            const isDisabled = !isSelected && atLimit;
                            return (
                                <button
                                    key={`blizzard-media-icon-${icon.id}`}
                                    type="button"
                                    onClick={() => toggleIcon(icon)}
                                    disabled={isDisabled}
                                    className={`relative inline-flex items-center rounded p-0.5 transition-all ${
                                        isSelected
                                            ? "ring-2 ring-green-500 ring-offset-1 ring-offset-brown-900"
                                            : isDisabled
                                              ? "cursor-not-allowed opacity-40"
                                              : "hover:bg-brown-700"
                                    }`}
                                    title={icon.name}
                                >
                                    <span className="sr-only">{icon.name}</span>
                                    <Tooltip text={icon.name}>
                                        <img src={icon.url} alt={icon.name} className="h-8 w-8 rounded-sm" />
                                    </Tooltip>
                                    {isSelected && (
                                        <span className="absolute -right-1 -top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-green-500 shadow">
                                            <Icon icon="check" style="solid" className="text-[7px] text-white" />
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                        {icons.length === 0 && (
                            <p className="col-span-10 py-8 text-center text-sm text-brown-400">No icons found.</p>
                        )}
                    </div>
                )}
            </div>

            {/* Pagination */}
            <Pagination
                links={paginatorLinks}
                meta={paginatorMeta}
                onPageChange={handlePage}
                className="border-t border-brown-700 pt-3"
            />

            {/* Footer: selection count + confirm */}
            <div className="mt-3 flex items-center justify-between border-t border-brown-700 pt-3">
                <span className="text-sm text-brown-400">
                    {selectionCount === 0 ? (
                        maxSelections === 1 ? (
                            "No icon selected"
                        ) : (
                            `0 / ${maxSelections} selected`
                        )
                    ) : maxSelections === 1 ? (
                        <span className="text-green-400">1 icon selected</span>
                    ) : (
                        <span className={atLimit ? "text-green-400" : "text-amber-300"}>
                            {selectionCount} / {maxSelections} selected
                        </span>
                    )}
                </span>

                <button
                    type="button"
                    disabled={selectionCount === 0}
                    onClick={handleConfirm}
                    className="rounded bg-amber-600 px-4 py-1.5 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:opacity-40"
                >
                    {maxSelections === 1
                        ? "Select"
                        : `Select ${selectionCount > 0 ? selectionCount : ""} icon${selectionCount !== 1 ? "s" : ""}`.trim()}
                </button>
            </div>
        </div>
    );
}
