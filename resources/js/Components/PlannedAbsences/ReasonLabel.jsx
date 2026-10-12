import Icon from "@/Components/FontAwesome/Icon";

/**
 * An absence reason's icon and label.
 */
export default function ReasonLabel({ reason, className }) {
    return (
        <span className={`flex items-center gap-1.5 ${className ?? ""}`}>
            <Icon icon={reason.icon} style="solid" className="h-[1em] text-sky-400" aria-hidden="true" />
            {reason.label}
        </span>
    );
}
