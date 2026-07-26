import { useState, useRef, useEffect } from "react";
import { Dialog, DialogBackdrop, DialogPanel } from "@headlessui/react";
import { router, usePage } from "@inertiajs/react";
import axios from "axios";
import Icon from "@/Components/FontAwesome/Icon";
import SearchResultRow from "@/Components/Search/SearchResultRow";
import useDebouncedValue from "@/Hooks/useDebouncedValue";

const MIN_QUERY_LENGTH = 2;

function ResultSkeleton() {
    return (
        <div className="animate-pulse space-y-1 p-2">
            {[0, 1, 2].map((i) => (
                <div key={i} className="flex items-center gap-4 rounded p-2">
                    <div className="h-8 w-8 flex-none rounded bg-brown-700" />
                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                        <div className="h-3 w-1/3 rounded bg-brown-700" />
                        <div className="h-4 w-2/3 rounded bg-brown-700" />
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function SearchPalette({ open, onClose }) {
    const page = usePage();
    // Scoping key: see LootBiasToolController::show, which renders this component name.
    const raidScope =
        page.component === "Loot/Raids/Show" && page.props.raid?.data?.id
            ? { id: page.props.raid.data.id, name: page.props.raid.data.name }
            : null;

    const [query, setQuery] = useState("");
    const [results, setResults] = useState([]);
    const [total, setTotal] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const [scopeDismissed, setScopeDismissed] = useState(false);

    const inputRef = useRef(null);
    const listRef = useRef(null);
    const abortRef = useRef(null);

    const debouncedQuery = useDebouncedValue(query, 300);
    const activeRaidId = scopeDismissed ? null : (raidScope?.id ?? null);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        } else {
            setQuery("");
            setResults([]);
            setTotal(0);
            setHighlightedIndex(-1);
            setScopeDismissed(false);
        }
    }, [open]);

    useEffect(() => {
        abortRef.current?.abort();

        const trimmed = debouncedQuery.trim();
        if (trimmed.length < MIN_QUERY_LENGTH) {
            setResults([]);
            setTotal(0);
            setIsLoading(false);
            return;
        }

        const controller = new AbortController();
        abortRef.current = controller;
        setIsLoading(true);

        axios
            .get(route("api.search"), {
                params: { q: trimmed, raid_id: activeRaidId },
                signal: controller.signal,
            })
            .then((res) => {
                setResults(res.data.data ?? []);
                setTotal(res.data.total ?? 0);
                setHighlightedIndex(-1);
            })
            .catch((err) => {
                if (!axios.isCancel(err)) {
                    setResults([]);
                    setTotal(0);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) setIsLoading(false);
            });

        return () => controller.abort();
    }, [debouncedQuery, activeRaidId]);

    useEffect(() => {
        if (highlightedIndex < 0) return;
        listRef.current?.querySelector(`#search-palette-option-${highlightedIndex}`)?.scrollIntoView({ block: "nearest" });
    }, [highlightedIndex]);

    const goToResults = () => {
        const trimmed = query.trim();
        if (trimmed.length < MIN_QUERY_LENGTH) return;
        onClose();
        router.visit(route("search", { q: trimmed, raid_id: activeRaidId }));
    };

    const handleKeyDown = (e) => {
        switch (e.key) {
            case "ArrowDown":
                e.preventDefault();
                setHighlightedIndex((prev) => (prev < results.length - 1 ? prev + 1 : prev));
                break;
            case "ArrowUp":
                e.preventDefault();
                setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : -1));
                break;
            case "Enter":
                e.preventDefault();
                if (highlightedIndex >= 0 && results[highlightedIndex]) {
                    const item = results[highlightedIndex];
                    onClose();
                    router.visit(route("loot.items.show", { item: item.id, slug: item.slug }));
                } else {
                    goToResults();
                }
                break;
        }
    };

    return (
        <Dialog open={open} onClose={onClose} initialFocus={inputRef} className="relative z-50">
            <DialogBackdrop
                transition
                className="fixed inset-0 bg-black/60 backdrop-blur-sm transition duration-300 ease-out data-closed:opacity-0"
            />

            <div className="fixed inset-0 z-10 flex items-start justify-center overflow-y-auto px-4 pt-[10vh] pb-6">
                <DialogPanel
                    transition
                    className="w-full max-w-2xl transform overflow-hidden rounded-lg border border-primary bg-brown bg-brown-texture shadow-xl transition duration-300 ease-out data-closed:translate-y-4 data-closed:opacity-0 data-closed:scale-95"
                >
                    <div className="flex items-center gap-2 border-b border-amber-600/50 px-4 py-3">
                        <Icon icon="search" style="solid" className="h-4 w-4 flex-none text-gray-400" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder="Search items..."
                            role="combobox"
                            aria-expanded={results.length > 0}
                            aria-controls="search-palette-listbox"
                            aria-autocomplete="list"
                            aria-activedescendant={
                                highlightedIndex >= 0 ? `search-palette-option-${highlightedIndex}` : undefined
                            }
                            className="w-full border-none bg-transparent text-white placeholder-gray-500 focus:border-none focus:ring-0 focus:outline-hidden"
                        />
                        {raidScope && !scopeDismissed && (
                            <button
                                type="button"
                                onClick={() => setScopeDismissed(true)}
                                className="inline-flex flex-none items-center gap-1 rounded bg-amber-600/20 px-2 py-1 text-xs font-semibold text-amber-500 hover:bg-amber-600/30"
                            >
                                {raidScope.name}
                                <Icon icon="times" style="solid" className="h-3 w-3" />
                            </button>
                        )}
                    </div>

                    {isLoading && <ResultSkeleton />}

                    {!isLoading && query.trim().length >= MIN_QUERY_LENGTH && results.length === 0 && (
                        <p className="px-4 py-6 text-center text-sm text-gray-400">No items found</p>
                    )}

                    {!isLoading && results.length > 0 && (
                        <div
                            ref={listRef}
                            id="search-palette-listbox"
                            role="listbox"
                            className="max-h-96 space-y-1 overflow-auto p-2"
                        >
                            {results.map((item, index) => (
                                <SearchResultRow
                                    key={item.id}
                                    item={item}
                                    index={index}
                                    isHighlighted={index === highlightedIndex}
                                    onMouseEnter={() => setHighlightedIndex(index)}
                                />
                            ))}
                        </div>
                    )}

                    {!isLoading && query.trim().length >= MIN_QUERY_LENGTH && total > 0 && (
                        <button
                            type="button"
                            onClick={goToResults}
                            className="w-full border-t border-amber-600/50 px-4 py-2 text-left text-sm text-amber-500 transition-colors hover:bg-amber-600/20"
                        >
                            {`See all ${total} results`}
                        </button>
                    )}
                </DialogPanel>
            </div>
        </Dialog>
    );
}
