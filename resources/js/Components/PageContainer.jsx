export default function PageContainer({ padding = 'py-12', className = '', children }) {
    return (
        <div className={`${padding} text-white`}>
            <div className={`container mx-auto px-4 ${className}`}>
                {children}
            </div>
        </div>
    );
}
