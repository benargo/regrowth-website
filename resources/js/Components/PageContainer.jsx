export default function PageContainer({ padding = "py-12", className = "", children }) {
    return (
        <div className={`${padding} text-white`}>
            <main className={`container mx-auto px-4 ${className}`}>{children}</main>
        </div>
    );
}
