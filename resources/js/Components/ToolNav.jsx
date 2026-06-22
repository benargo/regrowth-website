export default function ToolNav({ children }) {
    return (
        <nav className="bg-brown-900 shadow">
            <div className="container mx-auto px-4">
                <div className="flex min-h-12 flex-row flex-wrap items-center justify-between gap-x-2">{children}</div>
            </div>
        </nav>
    );
}
