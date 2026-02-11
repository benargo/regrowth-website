export default function Tooltip({ children, text, position = "top", ...props }) {
    const positionClasses = {
        top: "bottom-full left-1/2 mb-2 -translate-x-1/2",
        bottom: "top-full left-1/2 mt-2 -translate-x-1/2",
        left: "right-full top-1/2 mr-2 -translate-y-1/2",
        right: "left-full top-1/2 ml-2 -translate-y-1/2",
    };

    const arrowClasses = {
        top: "left-1/2 top-full -translate-x-1/2 border-t-gray-900",
        bottom: "left-1/2 bottom-full -translate-x-1/2 border-b-gray-900",
        left: "left-full top-1/2 -translate-y-1/2 border-l-gray-900",
        right: "right-full top-1/2 -translate-y-1/2 border-r-gray-900",
    };

    return (
        <div className="group/tooltip relative inline-block" {...props}>
            {children}
            <div
                className={`pointer-events-none absolute whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition-opacity group-hover/tooltip:opacity-100 ${positionClasses[position]}`}
            >
                {text}
                <div
                    className={`absolute border-4 border-transparent ${arrowClasses[position]}`}
                ></div>
            </div>
        </div>
    );
}
