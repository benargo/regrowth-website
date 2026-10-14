import { useRef, useState } from "react";
import { useHttp, usePage } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";

const RESOLUTION_ERRORS = {
    not_in_guild: "That person is no longer in the Discord server.",
    failed: "Couldn't load that Discord member. Pick them again.",
};

/**
 * Makes sure a Discord member picked in UserSearch has a users row before the form is saved.
 * `resolve(member)` posts to `api.discord.members.resolve`; a `ready` response resolves the member
 * at once, and a `pending` one keeps `isResolving` true until the DiscordMemberResolved broadcast
 * for that member arrives on the user's private channel. `resolve(null)` clears the selection.
 *
 * @returns {{ resolve: (member: ?{ id: string }) => void, isResolving: boolean, resolutionError: ?string }}
 */
export default function useDiscordMemberResolution() {
    const http = useHttp({});
    const userId = usePage().props.auth?.user?.id;
    const pendingMemberIdRef = useRef(null);
    const [isResolving, setIsResolving] = useState(false);
    const [resolutionError, setResolutionError] = useState(null);

    function settle(error = null) {
        pendingMemberIdRef.current = null;
        setIsResolving(false);
        setResolutionError(error);
    }

    useEcho(
        `App.Models.User.${userId}`,
        ".DiscordMemberResolved",
        (payload) => {
            if (payload.memberId !== pendingMemberIdRef.current) {
                return;
            }

            settle(RESOLUTION_ERRORS[payload.status] ?? null);
        },
        [userId],
    );

    function resolve(member) {
        http.cancel();

        if (!member) {
            settle();
            return;
        }

        pendingMemberIdRef.current = member.id;
        setIsResolving(true);
        setResolutionError(null);

        const settleIfCurrent = (error = null) => {
            if (pendingMemberIdRef.current === member.id) {
                settle(error);
            }
        };

        http.post(route("api.discord.members.resolve", member.id), {
            onSuccess: (response) => {
                if (response.status === "ready") {
                    settleIfCurrent();
                }
            },
            onHttpException: () => settleIfCurrent(RESOLUTION_ERRORS.failed),
            onNetworkError: () => settleIfCurrent(RESOLUTION_ERRORS.failed),
        }).catch(() => {});
    }

    return { resolve, isResolving, resolutionError };
}
