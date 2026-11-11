import axios from 'axios';
import { http } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import refreshApiToken, { shouldRefreshApiToken, withApiTokenRetry } from '@/Helpers/refreshApiToken';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Refresh the API token cookie and retry once when an axios /api request returns 401.
 */
window.axios.interceptors.response.use(undefined, async (error) => {
    const config = error.config;

    if (!config || config.hasRetriedApiToken || !shouldRefreshApiToken(error, config.url)) {
        throw error;
    }

    if (!(await refreshApiToken())) {
        throw error;
    }

    return window.axios({ ...config, hasRetriedApiToken: true });
});

/**
 * Refresh the API token cookie and retry once when a `useHttp` /api request returns 401.
 */
http.setClient(withApiTokenRetry(http.getClient()));

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            window.axios
                .post('/api/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                .then((response) => callback(null, response.data))
                .catch((error) => callback(error));
        },
    }),
});
