import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";

const roleIcons = {
    leader: "crown",
    loot_master: "sack",
};

const roleNames = {
    leader: "Raid Leader",
    loot_master: "Master Looter",
};

export default function RoleBadge({ role }) {
    const icon = roleIcons[role];

    if (!icon) {
        return null;
    }

    return (
        <Tooltip text={roleNames[role]}>
            <span className="p-0.5 text-xs text-amber-400">
                <Icon icon={icon} style="solid" />
            </span>
        </Tooltip>
    );
}
