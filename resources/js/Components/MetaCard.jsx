import Icon from "@/Components/FontAwesome/Icon";

export function MetaItem({ icon, children }) {
    return (
        <div className="flex items-center gap-2 text-sm text-gray-300">
            {icon && <Icon icon={icon} style="solid" className="w-4 text-body-muted" />}
            {children}
        </div>
    );
}

export default function MetaCard({ children }) {
    return (
        <div className="mb-8 rounded border border-line/30 bg-surface/50 p-4">
            <div className="flex flex-wrap gap-x-8 gap-y-3">{children}</div>
        </div>
    );
}
