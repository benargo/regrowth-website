export default function SharedHeader({ title, subtitle, backgroundClass = 'bg-masthead' }) {
    return (
        <header className={`${backgroundClass} h-64 md:h-96 text-white`}>
            <div className="w-full h-full px-4 bg-black/50 py-8 flex items-center">
                <div className="container mx-auto text-center">
                    <h1 className="text-4xl font-bold">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="mt-2 text-xl text-gray-300">{subtitle}</p>
                    )}
                </div>
            </div>
        </header>
    );
}