export default function Pill({
    children,
    bgColor = "bg-gray-600",
    textColor = "text-white",
    borderColor = undefined,
    style = undefined,
}) {
    function bgColorClass() {
        let bgColorClass = bgColor.replace(/\s/, "");
        if (!bgColorClass.startsWith("bg-")) {
            bgColorClass = "bg-" + bgColorClass;
        }
        return bgColorClass;
    }

    function textColorClass() {
        let textColorClass = textColor.replace(/\s/, "");
        if (!textColorClass.startsWith("text-")) {
            textColorClass = "text-" + textColorClass;
        }
        return textColorClass;
    }

    function borderClasses() {
        if (!borderColor || borderColor.length === 0) {
            return "";
        }
        let borderColorClass = borderColor.replace(/\s/, "");
        if (!borderColorClass.startsWith("border-")) {
            borderColorClass = "border-" + borderColorClass;
        }
        return `border border-0.5 ${borderColorClass}`;
    }

    return (
        <span
            style={style}
            className={`text-xs ${bgColorClass()} ${textColorClass()} ${borderClasses()} inline-block rounded-md px-2 py-0.5`}
        >
            {children}
        </span>
    );
}
