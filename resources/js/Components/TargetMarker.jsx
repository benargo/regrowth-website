const MARKERS = {
    star:     { col: 0, row: 0 },
    circle:   { col: 1, row: 0 },
    diamond:  { col: 2, row: 0 },
    triangle: { col: 3, row: 0 },
    moon:     { col: 0, row: 1 },
    square:   { col: 1, row: 1 },
    cross:    { col: 2, row: 1 },
    skull:    { col: 3, row: 1 },
};

export default function TargetMarker({ marker, size = 24, className }) {
    const entry = MARKERS[marker];

    if (!entry) {
        return null;
    }

    return (
        <span
            className={className}
            style={{
                display: "inline-block",
                width: size,
                height: size,
                backgroundImage: "url(/images/targetmarkers.webp)",
                backgroundSize: "400% 200%",
                backgroundPosition: `${entry.col * (100 / 3)}% ${entry.row * 100}%`,
                backgroundRepeat: "no-repeat",
            }}
            aria-label={marker}
            role="img"
        />
    );
}
