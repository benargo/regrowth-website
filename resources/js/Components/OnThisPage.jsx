import { useEffect, useState } from "react";

/**
 * Pixels from the viewport top below which a section counts as "being read":
 * clears the fixed site header plus this sticky bar on small screens.
 */
const READING_LINE = 200;

/**
 * Track which section the reader is in: the last section whose top has
 * scrolled past the reading line, or the last section once the page bottoms out.
 */
function useActiveSection(ids) {
    const [activeId, setActiveId] = useState(ids[0] ?? null);
    const key = ids.join("|");

    useEffect(() => {
        let frame = null;

        const update = () => {
            frame = null;
            const elements = ids.map((id) => document.getElementById(id)).filter(Boolean);

            if (elements.length === 0) {
                return;
            }

            const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
            const passed = elements.filter((element) => element.getBoundingClientRect().top <= READING_LINE);
            const current = atBottom ? elements.at(-1) : (passed.at(-1) ?? elements[0]);

            setActiveId(current.id);
        };

        const schedule = () => {
            frame ??= requestAnimationFrame(update);
        };

        update();
        window.addEventListener("scroll", schedule, { passive: true });
        window.addEventListener("resize", schedule);

        return () => {
            window.removeEventListener("scroll", schedule);
            window.removeEventListener("resize", schedule);
            cancelAnimationFrame(frame);
        };
    }, [key]);

    return activeId;
}

/**
 * In-page section links. A sticky vertical rail on large viewports (give it a
 * width via `className`), and a sticky, wrapping row above the content on
 * smaller ones. `children` render beneath the links, e.g. a save status.
 *
 * @param {{ sections: Array<{ value: string, label: string }>, className?: string, children?: React.ReactNode }} props
 */
export default function OnThisPage({ sections, className = "", children }) {
    const activeId = useActiveSection(sections.map((section) => section.value));

    return (
        <aside
            className={`bg-ground-900 border-ink-700/60 sticky top-24 z-10 -my-2 flex flex-wrap items-center justify-between gap-x-6 rounded-lg border p-2 shadow-lg shadow-black/30 lg:top-28 lg:my-0 lg:flex-col lg:flex-nowrap lg:items-stretch lg:justify-start lg:gap-4 lg:gap-y-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:px-0 lg:py-0 lg:shadow-none ${className}`}
        >
            <nav aria-label="On this page" className="min-w-0">
                <ul className="lg:border-ink-700 flex flex-wrap gap-2 text-sm lg:flex-col lg:flex-nowrap lg:gap-0 lg:border-l">
                    {sections.map((section) => {
                        const isActive = section.value === activeId;

                        return (
                            <li key={section.value} className="flex lg:-ml-px">
                                <a
                                    href={`#${section.value}`}
                                    aria-current={isActive ? "location" : undefined}
                                    className={`focus-visible:outline-ink-400 w-full rounded-md border px-3 py-1.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 lg:rounded-none lg:border-y-0 lg:border-r-0 lg:border-l-2 lg:bg-transparent lg:px-0 lg:pl-4 ${
                                        isActive
                                            ? "border-primary bg-primary/15 text-primary"
                                            : "bg-ground-800 border-ink-700/60 text-secondary-300 hover:border-ink-500 hover:bg-ground-700 hover:text-white lg:border-transparent lg:hover:bg-transparent"
                                    }`}
                                >
                                    {section.label}
                                </a>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            {children}
        </aside>
    );
}
