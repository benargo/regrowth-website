/**
 * Display labels for GRM upload sources, upload outcomes and row reasons, keyed by their enum values.
 */
export const SOURCE_LABELS = {
    manual: "Manual upload",
    desktop: "Desktop sync",
};

export const OUTCOME_LABELS = {
    received: "Queued",
    processing: "Processing",
    processed: "Processed",
    no_changes: "No changes",
    superseded: "Superseded",
    failed: "Failed",
    staging: "In progress",
    abandoned: "Abandoned",
    expired: "Expired",
};

export const OUTCOME_TONES = {
    processed: "text-green-400",
    no_changes: "text-gray-300",
    failed: "text-red-400",
    abandoned: "text-gray-400",
    expired: "text-gray-400",
    superseded: "text-gray-400",
    received: "text-amber-200",
    processing: "text-amber-200",
    staging: "text-amber-200",
};

export const REASON_LABELS = {
    created: "Created",
    updated: "Updated",
    renamed: "Renamed",
    main_changed: "Main changed",
    detached: "Detached from old character",
    left: "Left the guild",
    rejoined: "Rejoined the guild",
    below_minimum_level: "Below the minimum level",
    not_tracked: "Not tracked",
    not_confirmed_in_guild: "Not confirmed in the guild",
    placeholder_name: "Placeholder name",
    unknown_class: "Unknown class",
    unknown_race: "Unknown race",
    unknown_rank: "Unknown rank",
    name_clash: "Name clash",
    error: "Error",
};

export const ROW_STATUS_LABELS = {
    processed: "Processed",
    skipped: "Skipped",
    warning: "Warning",
    failed: "Failed",
};
