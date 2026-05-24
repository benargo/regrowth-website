import { usePermission } from '@/Hooks/usePermission';

export { usePermission };

export function Can({ permission, children }) {
    return usePermission(permission) ? children : null;
}

export function Cannot({ permission, children }) {
    return usePermission(permission) ? null : children;
}
