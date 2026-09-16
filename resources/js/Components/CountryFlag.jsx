import * as flags from "country-flag-icons/react/3x2";

const SIZE_CLASS = {
    4: "h-4 w-6",
    5: "h-5 w-7",
};

export default function CountryFlag({ countryCode, title, size = 4, className = "" }) {
    if (!countryCode) {
        return null;
    }

    const Flag = flags[countryCode.toUpperCase()];

    if (!Flag) {
        return null;
    }

    const sizeClass = SIZE_CLASS[size] ?? SIZE_CLASS[4];

    return (
        <Flag
            title={title}
            aria-hidden={title ? undefined : "true"}
            className={`${sizeClass} rounded-sm ${className}`}
        />
    );
}
