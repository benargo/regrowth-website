const maxWidthClasses = {
    lg: "max-w-lg",
    xl: "max-w-xl",
    "2xl": "max-w-2xl",
};

export default function FormContainer({ maxWidth = "xl", children }) {
    return (
        <div className="py-12 text-white">
            <main className={`container mx-auto ${maxWidthClasses[maxWidth] ?? "max-w-xl"} px-4`}>
                {children}
            </main>
        </div>
    );
}
