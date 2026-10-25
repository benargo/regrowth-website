import { usePage } from "@inertiajs/react";

/**
 * A function reporting whether the viewer holds a given permission.
 */
export function usePermissionCheck() {
    const permissions = usePage().props.auth?.permissions ?? [];

    return (permission) => permissions.includes(permission);
}

export function usePermission(permission) {
    return usePermissionCheck()(permission);
}
