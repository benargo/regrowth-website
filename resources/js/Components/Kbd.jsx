/**
 * A keyboard shortcut in running text, kept on one line so combinations like
 * "Ctrl-A" never break at the hyphen.
 */
export default function Kbd({ children }) {
    return <kbd className="font-sans font-semibold whitespace-nowrap">{children}</kbd>;
}
