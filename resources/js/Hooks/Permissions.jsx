import { usePage } from '@inertiajs/react';

/**
 * Returns true if the authenticated user has the given permission.
 *
 * Reads from the shared `auth.permissions` array provided by Inertia, so callers
 * do not need to access or destructure page props themselves.
 *
 * @param {string} permission - The permission name to check, e.g. 'view-officer-dashboard'.
 * @returns {boolean}
 */
export default function usePermission(permission) {
    const { auth } = usePage().props;

    return auth?.permissions?.includes(permission) ?? false;
}