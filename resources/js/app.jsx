import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, router } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

const THEME_COLORS = { forever: "#003c4e", classic: "#4a3d32" };

router.on("navigate", (event) => {
    const isHomepage = event.detail.page.component === "Home";

    document
        .querySelector('meta[name="theme-color"]')
        ?.setAttribute("content", isHomepage ? THEME_COLORS.forever : THEME_COLORS.classic);
    document.body.classList.toggle("bg-forever-800", isHomepage);
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob("./Pages/**/*.jsx")),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: "#f8b700",
    },
});
