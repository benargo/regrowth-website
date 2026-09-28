import { useRef, useState } from "react";
import { router } from "@inertiajs/react";
import Relationships from "@/Components/Datasets/Relationships";
import { SaveButton } from "@/Components/FormControls";
import RankLadder from "@/Components/GuildRanks/RankLadder";
import useAutosave, { autosaveVisit } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/** The most ranks a WoW guild can have. */
const MAX_GUILD_RANKS = 10;

let newRowCount = 0;

function toRow(rank) {
    return { ...rank, key: `rank-${rank.id}` };
}

function newRow(name) {
    newRowCount += 1;

    return { key: `new-${newRowCount}`, id: null, name, count_attendance: true, characters_count: 0 };
}

/** What the server stores for each row, in rank order. */
function payload(rows) {
    return rows.map(({ id, name, count_attendance }) => ({ id, name, count_attendance }));
}

/**
 * Fold the server's ranks, saved from `sent`, into the rows now on screen.
 * The server keeps the order it was sent, so saved[i] is sent[i]: a new row
 * picks up its id from there, and a row left untouched since the save takes
 * the server's spelling of its name (names are title-cased). A row edited
 * while the save was in flight keeps the officer's edit for the next save.
 */
function reconcileRanks(rows, sent, saved) {
    return rows.map((row) => {
        const index = sent.findIndex((sentRow) => sentRow.key === row.key);
        const savedRank = saved[index];

        if (index === -1 || !savedRank) {
            return row;
        }

        const untouched = sent[index].name === row.name && sent[index].count_attendance === row.count_attendance;

        return {
            ...row,
            id: savedRank.id,
            characters_count: savedRank.characters_count,
            ...(untouched ? { name: savedRank.name } : {}),
        };
    });
}

/**
 * Split the server's errors into the section's own message and one message
 * per row. Row errors are indexed by the list that was sent, so they are keyed
 * to the sent rows: a row dragged while the save was in flight keeps its own.
 */
function splitErrors(visitErrors, sent) {
    const rows = {};

    for (const [field, message] of Object.entries(visitErrors)) {
        const match = field.match(/^guild_ranks\.(\d+)\./);
        const row = match ? sent[Number(match[1])] : null;

        if (row && !(row.key in rows)) {
            rows[row.key] = message;
        }
    }

    return { section: visitErrors.guild_ranks, rows };
}

export default function GuildRanksSection({
    gameVersion,
    relationships,
    submitLabel = "Save guild ranks",
    onSaved,
    autosave = false,
}) {
    const url = route("management.game-versions.update", gameVersion.id);
    const [rows, setRows] = useState(() => relationships.guild_ranks.ranks.map(toRow));
    const [errors, setErrors] = useState({ rows: {} });
    const [processing, setProcessing] = useState(false);
    const latestRows = useRef(rows);
    const savedPayload = useRef(JSON.stringify(payload(rows)));

    function update(change) {
        const nextRows = typeof change === "function" ? change(latestRows.current) : change;
        latestRows.current = nextRows;
        setRows(nextRows);
    }

    function save(headers = {}) {
        const sent = latestRows.current;
        setProcessing(true);

        return autosaveVisit(router, "patch", url, {
            data: { guild_ranks: payload(sent) },
            headers,
            onSuccess: (page) => {
                const saved = page.props.relationships.guild_ranks.ranks;
                savedPayload.current = JSON.stringify(payload(saved));
                update(reconcileRanks(latestRows.current, sent, saved));
                setErrors({ rows: {} });
            },
            onError: (visitErrors) => setErrors(splitErrors(visitErrors, sent)),
            onFinish: () => setProcessing(false),
        });
    }

    const { schedule } = useAutosave({
        key: "guild-ranks",
        isDirty: () => JSON.stringify(payload(latestRows.current)) !== savedPayload.current,
        save: () => save({ "X-Autosave": "1" }),
        delay: AUTOSAVE_DELAY,
        enabled: autosave,
    });

    function commit(change) {
        update(change);
        schedule();
    }

    function handleSubmit(event) {
        event.preventDefault();
        save().then((result) => {
            if (result.ok) {
                onSaved?.();
            }
        });
    }

    return (
        <Relationships
            id="guild-ranks"
            title="Guild ranks"
            description="List the guild's ranks in the same order as the in-game guild control panel, with the guild master at the top. Drag a rank to move it. Deleted or moved ranks are cleared from characters until the guild roster refreshes, about a minute after you finish editing."
        >
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
                {errors.section && (
                    <p className="text-sm text-red-400" role="alert">
                        {errors.section}
                    </p>
                )}
                <RankLadder
                    rows={rows}
                    onChange={update}
                    onCommit={commit}
                    errors={errors.rows}
                    maxRanks={MAX_GUILD_RANKS}
                    newRow={newRow}
                />
                {!autosave && <SaveButton processing={processing} label={submitLabel} />}
            </form>
        </Relationships>
    );
}
