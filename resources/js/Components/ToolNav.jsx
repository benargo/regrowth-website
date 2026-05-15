export default function ToolNav({ children }) {
    return (
        <nav className="bg-brown-900 shadow">
            <div className="container mx-auto px-4">
                <div className="flex min-h-12 flex-col items-center justify-between md:flex-row">{children}</div>
            </div>
        </nav>
    );
}
