/**
 * Strips diacritical marks from a string and lowercases it so that, e.g.,
 * "smor" and "smör" both match "Smörgås".
 */
export default function normaliseCharacterName(name) {
    return name
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase();
}