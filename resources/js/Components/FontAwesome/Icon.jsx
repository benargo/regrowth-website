import React, { Suspense, lazy } from "react";

const FontAwesomeRegular = lazy(() => import("./FontAwesomeRegular"));
const FontAwesomeSolid = lazy(() => import("./FontAwesomeSolid"));
const FontAwesomeLight = lazy(() => import("./FontAwesomeLight"));
const FontAwesomeBrands = lazy(() => import("./FontAwesomeBrands"));

export function faRegular({ icon, className = "", ...props }) {
    return (
        <Suspense fallback={<span className={className}></span>}>
            <FontAwesomeRegular icon={icon} className={className} {...props} />
        </Suspense>
    );
}

export function faSolid({ icon, className = "", ...props }) {
    return (
        <Suspense fallback={<span className={className}></span>}>
            <FontAwesomeSolid icon={icon} className={className} {...props} />
        </Suspense>
    );
}

export function faLight({ icon, className = "", ...props }) {
    return (
        <Suspense fallback={<span className={className}></span>}>
            <FontAwesomeLight icon={icon} className={className} {...props} />
        </Suspense>
    );
}

export function faBrands({ icon, className = "", ...props }) {
    return (
        <Suspense fallback={<span className={className}></span>}>
            <FontAwesomeBrands icon={icon} className={className} {...props} />
        </Suspense>
    );
}

export default function Icon({ icon, style = "solid", className = "", ...props }) {
    let IconComponent;
    switch (style) {
        case "regular":
            IconComponent = faRegular;
            break;
        case "light":
            IconComponent = faLight;
            break;
        case "brands":
            IconComponent = faBrands;
            break;
        case "solid":
        default:
            IconComponent = faSolid;
    }
    return <IconComponent icon={icon} className={className} {...props} />;
}
