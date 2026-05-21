const maxWidthClasses = {
    lg: "max-w-lg",
    xl: "max-w-xl",
    "2xl": "max-w-2xl",
};

export default function FormContainer({ maxWidth = "xl", padding = "py-12", className = "", children }) {
    return (
        <div className={`${padding} text-white`}>
            <main className={`container mx-auto ${maxWidthClasses[maxWidth] ?? "max-w-xl"} px-4 ${className}`}>
                {children}
            </main>
        </div>
    );
}
