import { useState, useRef, useEffect, useCallback } from "react";
import { createPortal } from "react-dom";
import { router } from "@inertiajs/react";
import axios from "axios";
import SearchInput from "@/Components/Search/SearchInput";
import ItemRow from "@/Components/Loot/ItemRow";
import useDebouncedValue from "@/Hooks/useDebouncedValue";

const MIN_QUERY_LENGTH = 2;

export default function SearchBar({ weightThreshold }) {
    const [query, setQuery] = useState("");
    const [results, setResults] = useState([]);
    const [total, setTotal] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const [position, setPosition] = useState(null);

    const anchorRef = useRef(null);
    const panelRef = useRef(null);
    const inputRef = useRef(null);
    const abortRef = useRef(null);

    const debouncedQuery = useDebouncedValue(query, 300);

    // Measure the anchor so the portalled panel stays attached to it.
    const updatePosition = useCallback(() => {
        if (!anchorRef.current) return;
        const rect = anchorRef.current.getBoundingClientRect();
        setPosition({ top: rect.bottom + 4, left: rect.left, width: rect.width });
    }, []);

    useEffect(() => {
        if (!isOpen) return;
        updatePosition();
        // Capture phase so nested scroll containers also fire.
        window.addEventListener("scroll", updatePosition, true);
        window.addEventListener("resize", updatePosition);
        return () => {
            window.removeEventListener("scroll", updatePosition, true);
            window.removeEventListener("resize", updatePosition);
        };
    }, [isOpen, updatePosition]);

    // Ctrl/Cmd+K opens and focuses; Escape closes and blurs.
    useEffect(() => {
        const handler = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
                e.preventDefault();
                setIsOpen(true);
                inputRef.current?.focus();
                return;
            }
            if (e.key === "Escape") {
                setIsOpen(false);
                inputRef.current?.blur();
            }
        };
        document.addEventListener("keydown", handler);
        return () => document.removeEventListener("keydown", handler);
    }, []);

    // Click-outside closes, ignoring clicks inside the portalled panel.
    useEffect(() => {
        const handler = (e) => {
            if (anchorRef.current?.contains(e.target)) return;
            if (panelRef.current?.contains(e.target)) return;
            setIsOpen(false);
        };
        document.addEventListener("mousedown", handler);
        return () => document.removeEventListener("mousedown", handler);
    }, []);

    // Fetch on each debounced change, cancelling the prior request.
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
            .get(route("api.search"), { params: { q: trimmed }, signal: controller.signal })
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
    }, [debouncedQuery]);

    const goToResults = () => {
        const trimmed = query.trim();
        if (trimmed.length < MIN_QUERY_LENGTH) return;
        setIsOpen(false);
        router.visit(route("search", { q: trimmed }));
    };

    const handleKeyDown = (e) => {
        if (!isOpen) return;

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
                    setIsOpen(false);
                    router.visit(route("loot.items.show", { item: item.id, slug: item.slug }));
                } else {
                    goToResults();
                }
                break;
        }
    };

    const showPanel = isOpen && position && query.trim().length >= MIN_QUERY_LENGTH;

    return (
        <div ref={anchorRef} onKeyDown={handleKeyDown}>
            <SearchInput
                inputRef={inputRef}
                value={query}
                onChange={(val) => {
                    setQuery(val);
                    setIsOpen(true);
                }}
                placeholder="Search items...  (Ctrl+K)"
            />

            {showPanel &&
                createPortal(
                    <div
                        ref={panelRef}
                        style={{ position: "fixed", top: position.top, left: position.left, width: position.width }}
                        className="bg-brown-800 z-50 overflow-hidden rounded border border-amber-600 shadow-xl"
                    >
                        {isLoading && <p className="px-4 py-3 text-sm text-gray-400">Searching...</p>}

                        {!isLoading && results.length === 0 && (
                            <p className="px-4 py-3 text-sm text-gray-400">No items found</p>
                        )}

                        {!isLoading && results.length > 0 && (
                            <div className="max-h-96 space-y-1 overflow-auto p-2">
                                {results.map((item, index) => (
                                    <div
                                        key={item.id}
                                        onMouseEnter={() => setHighlightedIndex(index)}
                                        className={index === highlightedIndex ? "rounded ring-1 ring-amber-500" : ""}
                                    >
                                        <ItemRow item={item} weightThreshold={weightThreshold} />
                                    </div>
                                ))}
                            </div>
                        )}

                        <button
                            type="button"
                            onClick={goToResults}
                            className="w-full border-t border-amber-600/50 px-4 py-2 text-left text-sm text-amber-500 transition-colors hover:bg-amber-600/20"
                        >
                            {total > 0 ? `See all ${total} results` : "See all results"}
                        </button>
                    </div>,
                    document.body,
                )}
        </div>
    );
}
