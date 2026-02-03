export default function Alert({ type = 'info', children }) {
    const typeClasses = {
        info: 'bg-blue-100 border-blue-500 text-blue-700',
        success: 'bg-green-100 border-green-500 text-green-700',
        warning: 'bg-yellow-100 border-yellow-500 text-yellow-700',
        error: 'bg-red-100 border-red-500 text-red-700',
    };

    return (
        <div
            className={`border-l-4 p-4 ${typeClasses[type] || typeClasses.info}`}
            role="alert"
        >
            <div className="flex items-center">
                <div className="flex-shrink-0">
                    {type === 'info' && <i className="fas fa-info-circle fa-lg"></i>}
                    {type === 'success' && <i className="fas fa-check-circle fa-lg"></i>}
                    {type === 'warning' && <i className="fas fa-exclamation-triangle fa-lg"></i>}
                    {type === 'error' && <i className="fas fa-times-circle fa-lg"></i>}
                </div>
                <div className="ml-3">
                    {children}
                </div>
            </div>
        </div>
    );
}