import { usePage } from "@inertiajs/react";

/**
 * A function reporting whether the viewer holds a given permission.
 */
export function usePermissionCheck() {
    const permissions = usePage().props.auth?.permissions ?? [];

    return (permission) => permissions.includes(permission);
}

/**
 * Whether the viewer holds the permission, or any one of a list of permissions.
 */
export function usePermission(permission) {
    const check = usePermissionCheck();

    return Array.isArray(permission) ? permission.some(check) : check(permission);
}
