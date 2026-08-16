const SIZE_CLASS = {
    4: "h-4 w-4",
    5: "h-5 w-5",
    6: "h-6 w-6",
    10: "h-10 w-10",
};

export default function SpecIcon({ specialization, playableClass, size = 5, className = "" }) {
    const src = specialization?.icon_url ?? playableClass?.icon_url;
    const alt = specialization?.name ?? playableClass?.name;

    if (!src) {
        return null;
    }

    const sizeClass = SIZE_CLASS[size] ?? SIZE_CLASS[5];

    return (
        <img
            src={src}
            alt={alt}
            className={`${sizeClass} rounded ${className}`}
        />
    );
}
