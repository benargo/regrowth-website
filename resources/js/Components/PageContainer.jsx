export default function PageContainer({ children }) {
    return (
        <div className="bg-fade-surface flex-1 py-12 text-white">
            <main className="container mx-auto px-4">{children}</main>
        </div>
    );
}
