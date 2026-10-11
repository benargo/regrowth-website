import { useState, useRef, useEffect } from "react";
import { router, usePage } from "@inertiajs/react";
import { Input } from "@headlessui/react";
import Icon from "@/Components/FontAwesome/Icon";
import { controlClassName } from "@/Components/FormControls";

/**
 * Searches the Discord server's members by partially reloading the page's optional `discord_members` prop.
 * Put it in a FormRow, which labels it and shows any error.
 */
export default function UserSearch({ id, value, onSelect, invalid = false }) {
    const results = usePage().props.discord_members ?? [];
    const [query, setQuery] = useState("");
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);
    const cancelTokenRef = useRef(null);
    const debounceRef = useRef(null);

    useEffect(() => {
        const handler = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener("mousedown", handler);
        return () => document.removeEventListener("mousedown", handler);
    }, []);

    function handleInputChange(e) {
        const val = e.target.value;
        setQuery(val);
        onSelect(null);

        clearTimeout(debounceRef.current);
        cancelTokenRef.current?.cancel();

        if (!val.trim()) {
            setIsOpen(false);
            return;
        }

        debounceRef.current = setTimeout(() => {
            setIsOpen(true);

            router.reload({
                only: ["discord_members"],
                data: { member_search: val, member_limit: 5 },
                preserveUrl: true,
                onCancelToken: (cancelToken) => (cancelTokenRef.current = cancelToken),
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            });
        }, 300);
    }

    function handleSelect(member) {
        onSelect(member);
        setQuery(member.nickname ?? member.username);
        setIsOpen(false);
    }

    function handleClear() {
        setQuery("");
        onSelect(null);
        setIsOpen(false);
        cancelTokenRef.current?.cancel();
        clearTimeout(debounceRef.current);
    }

    return (
        <div ref={containerRef} className="relative">
            <div className="relative">
                <Icon
                    icon="search"
                    style="solid"
                    className="text-secondary-400 pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2"
                />
                <Input
                    id={id}
                    type="text"
                    value={query}
                    onChange={handleInputChange}
                    placeholder="Search by username or nickname"
                    autoComplete="off"
                    spellCheck={false}
                    invalid={invalid}
                    className={`${controlClassName} pr-10 pl-11`}
                />
                {isLoading && (
                    <div className="border-secondary-500 absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-t-transparent" />
                )}
                {!isLoading && (query || value) && (
                    <button
                        type="button"
                        onClick={handleClear}
                        aria-label="Clear Discord user"
                        className="text-secondary-400 focus-visible:outline-ink-400 absolute inset-y-0 right-1 my-auto flex size-8 items-center justify-center rounded hover:text-white focus-visible:outline-2"
                    >
                        <Icon icon="times" style="solid" />
                    </button>
                )}
            </div>

            {isOpen && results.length > 0 && (
                <ul className="border-ink-600 bg-ground-800 absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded border shadow-lg">
                    {results.map((member) => (
                        <li key={member.id}>
                            <button
                                type="button"
                                onClick={() => handleSelect(member)}
                                className="hover:bg-ground-700 w-full px-4 py-2 text-left text-sm text-white transition-colors"
                            >
                                {member.nickname ?? member.username}
                                {member.nickname && (
                                    <span className="text-secondary-400 ml-2 text-xs">@{member.username}</span>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {isOpen && !isLoading && results.length === 0 && query.trim() && (
                <div className="border-ink-600 bg-ground-800 text-secondary-400 absolute z-50 mt-1 w-full rounded border px-4 py-2 text-sm shadow-lg">
                    No members found
                </div>
            )}
        </div>
    );
}
