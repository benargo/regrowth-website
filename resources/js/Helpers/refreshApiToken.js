import { router } from "@inertiajs/react";

let hasAuthenticatedUser = false;
let pendingRefresh = null;

router.on("navigate", (event) => {
    hasAuthenticatedUser = Boolean(event.detail.page.props.auth?.user);
});

/**
 * Whether a failed request was an /api call rejected with 401 on a page with a signed-in user.
 *
 * @param {{ response?: { status?: number } }} error
 * @param {?string} url
 * @returns {boolean}
 */
export function shouldRefreshApiToken(error, url) {
    return (
        hasAuthenticatedUser &&
        error?.response?.status === 401 &&
        new URL(url ?? "", window.location.origin).pathname.startsWith("/api/")
    );
}

/**
 * Request a fresh Passport API token cookie from `auth.api-token`. Concurrent calls share one request.
 * Resolves `true` once the cookie is reissued. Resolves `false` otherwise, reloading the page when the
 * session has ended.
 *
 * @returns {Promise<boolean>}
 */
export default function refreshApiToken() {
    pendingRefresh ??= window
        .fetch(route("auth.api-token"), {
            credentials: "same-origin",
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        })
        .then((response) => {
            if (response.status === 401) {
                window.location.reload();
            }

            return response.ok;
        })
        .catch(() => false)
        .finally(() => {
            pendingRefresh = null;
        });

    return pendingRefresh;
}

/**
 * Wrap an Inertia HTTP client so an /api request rejected with 401 refreshes the API token cookie and
 * retries once.
 *
 * @param {{ request: (config: object) => Promise<object> }} client
 * @returns {{ request: (config: object) => Promise<object> }}
 */
export function withApiTokenRetry(client) {
    return {
        async request(config) {
            try {
                return await client.request(config);
            } catch (error) {
                if (!shouldRefreshApiToken(error, config.url) || !(await refreshApiToken())) {
                    throw error;
                }

                return client.request(config);
            }
        },
    };
}
