export default function PageContainer({ children }) {
    return (
        <div className="py-12 text-white">
            <main className="container mx-auto px-4">{children}</main>
        </div>
    );
}
