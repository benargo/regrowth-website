import { usePage } from '@inertiajs/react';

export function usePermission(permission) {
    const { auth } = usePage().props;

    return auth?.permissions?.includes(permission) ?? false;
}
