import { useCallback } from "react";
import { router, usePage } from "@inertiajs/react";

let isLoadingNavigation = false;

/**
 * The `navigation` shared prop (undefined until first loaded, then kept across
 * visits until it expires or the game version or viewer changes) and a
 * `load()` that requests it with an async partial reload. `load()` does nothing
 * while the prop is present or a load from any instance is in flight.
 */
export default function useNavigationData() {
    const { navigation } = usePage().props;

    const load = useCallback(() => {
        if (navigation !== undefined || isLoadingNavigation) {
            return;
        }

        isLoadingNavigation = true;

        router.reload({
            only: ["navigation"],
            async: true,
            showProgress: false,
            onFinish: () => {
                isLoadingNavigation = false;
            },
        });
    }, [navigation]);

    return { navigation, load };
}
