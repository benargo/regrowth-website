export default function SharedHeader({ title, subtitle, backgroundClass = "bg-masthead" }) {
    return (
        <header className={`${backgroundClass} h-64 text-white md:h-96`}>
            <div className="flex h-full w-full items-center bg-black/50 px-4 py-8">
                <div className="container mx-auto">
                    <h1 className="text-center text-4xl font-bold">
                        {title}
                        {subtitle && <span className="mt-2 block text-xl text-gray-300">{subtitle}</span>}
                    </h1>
                </div>
            </div>
        </header>
    );
}
