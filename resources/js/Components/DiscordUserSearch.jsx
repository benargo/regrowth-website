import { useState, useRef, useEffect } from "react";
import axios from "axios";
import InputError from "@/Components/InputError";
import Icon from "@/Components/FontAwesome/Icon";

export default function DiscordUserSearch({ value, onSelect, error }) {
    const [query, setQuery] = useState("");
    const [results, setResults] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);
    const abortRef = useRef(null);
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
        abortRef.current?.abort();

        if (!val.trim()) {
            setResults([]);
            setIsOpen(false);
            return;
        }

        debounceRef.current = setTimeout(() => {
            const controller = new AbortController();
            abortRef.current = controller;
            setIsLoading(true);
            setIsOpen(true);

            axios
                .get(route("api.discord.guild.members.search"), {
                    params: { query: val, limit: 5 },
                    signal: controller.signal,
                })
                .then((res) => setResults(res.data))
                .catch((err) => {
                    if (!axios.isCancel(err)) {
                        setResults([]);
                    }
                })
                .finally(() => setIsLoading(false));
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
        setResults([]);
        setIsOpen(false);
        abortRef.current?.abort();
        clearTimeout(debounceRef.current);
    }

    return (
        <div ref={containerRef} className="relative">
            <div className="relative">
                <Icon icon="search" style="solid" className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
                <input
                    type="text"
                    value={query}
                    onChange={handleInputChange}
                    placeholder="Search by username or nickname..."
                    className="w-full rounded border border-amber-600 bg-brown-800 py-2 pl-10 pr-10 text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                />
                {isLoading && (
                    <div className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-gray-500 border-t-transparent" />
                )}
                {!isLoading && (query || value) && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                    >
                        <Icon icon="times" style="solid" />
                    </button>
                )}
            </div>

            {isOpen && results.length > 0 && (
                <ul className="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded border border-amber-600 bg-brown-800 shadow-lg">
                    {results.map((member) => (
                        <li key={member.id}>
                            <button
                                type="button"
                                onClick={() => handleSelect(member)}
                                className="w-full px-4 py-2 text-left text-sm text-white transition-colors hover:bg-brown-700"
                            >
                                {member.nickname ?? member.username}
                                {member.nickname && (
                                    <span className="ml-2 text-xs text-gray-400">@{member.username}</span>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {isOpen && !isLoading && results.length === 0 && query.trim() && (
                <div className="absolute z-50 mt-1 w-full rounded border border-amber-600 bg-brown-800 px-4 py-2 text-sm text-gray-400 shadow-lg">
                    No members found
                </div>
            )}

            <InputError message={error} className="mt-2" />
        </div>
    );
}
