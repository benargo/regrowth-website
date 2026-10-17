export default function PageContainer({ children }) {
    return (
        <div className="flex-1 py-12 text-white">
            <div className="container mx-auto px-4">{children}</div>
        </div>
    );
}
