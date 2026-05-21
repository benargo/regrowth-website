export default function Card({ title, headerRight, className = '', children }) {
    return (
        <div className={`rounded border border-amber-600 ${className}`}>
            {(title || headerRight) && (
                <div className="flex items-center justify-between border-b border-amber-600/40 px-4 py-3">
                    {title && <h3 className="font-semibold text-white">{title}</h3>}
                    {headerRight && <div>{headerRight}</div>}
                </div>
            )}
            <div className="p-4">
                {children}
            </div>
        </div>
    );
}
