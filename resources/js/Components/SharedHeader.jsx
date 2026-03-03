export default function SharedHeader({ title, backgroundClass = 'bg-masthead' }) {
    return (
        <header className={`${backgroundClass} h-64 md:h-96 text-white`}>
            <div className="w-full h-full px-4 bg-black/50 py-8 flex items-center">
                <div className="container mx-auto">
                    <h1 className="text-4xl font-bold text-center">
                        {title}
                    </h1>
                </div>
            </div>
        </header>
    );
}