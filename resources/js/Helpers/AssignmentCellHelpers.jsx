import MODEL_TYPES from "@/Helpers/AssignmentModelTypes";

function formatGroupsLabel(query) {
    if (!query) return "";

    if (/^\d+$/.test(query)) {
        return `Group ${query}`;
    }

    const rangeMatch = query.match(/^(\d+)-(\d+)$/);
    if (rangeMatch) {
        return `Groups ${query}`;
    }

    if (/^\d+(\s*,\s*\d+)+$/.test(query)) {
        const nums = query.split(",").map((s) => parseInt(s.trim(), 10));
        const last = nums.pop();
        if (nums.length === 0) return `Group ${last}`;
        if (nums.length === 1) return `Groups ${nums[0]} and ${last}`;
        return `Groups ${nums.join(", ")}, and ${last}`;
    }

    return String(query);
}

/**
 * Derives the display label, iconUrl, and slug from a resolved assignment side
 * ({ type, data } shape from EventAssignmentResource).
 */
export function labelFromSide(side) {
    if (!side || !side.type) return { label: "", iconUrl: null, slug: null };

    switch (side.type) {
        case MODEL_TYPES.CHARACTER:
            return {
                label: side.data?.name ?? "",
                iconUrl: side.data?.playable_class?.icon_url ?? null,
                slug: null,
            };
        case MODEL_TYPES.PLAYABLE_CLASS:
            return {
                label: side.data?.name ?? "",
                iconUrl: side.data?.icon_url ?? null,
                slug: null,
            };
        case MODEL_TYPES.SPELL:
            return {
                label: side.data?.name ?? "",
                iconUrl: side.data?.icon ?? null,
                slug: null,
            };
        case MODEL_TYPES.TARGET_MARKER:
            return {
                label: side.data?.name ?? "",
                iconUrl: null,
                slug: side.data?.slug ?? null,
            };
        case MODEL_TYPES.GROUPS:
            return { label: formatGroupsLabel(side.data), iconUrl: null, slug: null };
        case "string":
            return { label: String(side.data ?? ""), iconUrl: null, slug: null };
        default:
            return { label: String(side.data ?? ""), iconUrl: null, slug: null };
    }
}

/**
 * Converts the { type, data } resource side back to the flat { left_type, left_value }
 * storage shape used when PATCHing the API.
 */
export function storageFromSide(side) {
    if (!side || !side.type) return { left_type: null, left_value: "" };

    switch (side.type) {
        case MODEL_TYPES.CHARACTER:
            return { left_type: MODEL_TYPES.CHARACTER, left_value: String(side.data?.id ?? "") };
        case MODEL_TYPES.SPELL:
            return { left_type: MODEL_TYPES.SPELL, left_value: String(side.data?.id ?? "") };
        case MODEL_TYPES.TARGET_MARKER:
            return { left_type: MODEL_TYPES.TARGET_MARKER, left_value: side.data?.slug ?? "" };
        case "string":
            return { left_type: null, left_value: String(side.data ?? "") };
        default:
            return { left_type: null, left_value: String(side.data ?? "") };
    }
}

/**
 * Returns the Tailwind background colour class for an assignment side.
 */
export function colorClassFromSide(side) {
    if (!side || !side.type) return "";

    switch (side.type) {
        case MODEL_TYPES.CHARACTER:
            return `bg-playable-class-${side.data?.playable_class?.slug}`;
        case MODEL_TYPES.PLAYABLE_CLASS:
            return `bg-playable-class-${side.data?.slug}`;
        case MODEL_TYPES.SPELL:
            return side.data?.color ? `bg-${side.data.color}/40` : "";
        default:
            return "";
    }
}

/**
 * Returns the Tailwind text colour class for an assignment side.
 */
export function textClassFromSide(side) {
    if (side?.type === MODEL_TYPES.CHARACTER || side?.type === MODEL_TYPES.PLAYABLE_CLASS) {
        return "text-black";
    }
    return "text-white";
}
