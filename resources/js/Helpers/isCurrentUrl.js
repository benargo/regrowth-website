/**
 * Whether `href` points at the current page or one of its descendants, compared by pathname.
 * External origins never match, and "/" only matches the homepage itself.
 */
export default function isCurrentUrl(href, currentUrl) {
    if (!href) {
        return false;
    }

    const target = new URL(href, window.location.origin);

    if (target.origin !== window.location.origin) {
        return false;
    }

    const currentPath = new URL(currentUrl, window.location.origin).pathname.replace(/\/+$/, "") || "/";
    const targetPath = target.pathname.replace(/\/+$/, "") || "/";

    if (targetPath === "/") {
        return currentPath === "/";
    }

    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
}
