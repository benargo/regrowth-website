export default function SharedHeader({ title, backgroundClass = 'bg-masthead' }) {
    return (
        <div className={`${backgroundClass} py-24 text-white`}>
            <div className="container mx-auto px-4">
                <h1 className="text-4xl font-bold text-center">
                    {title}
                </h1>
            </div>
        </div>
    );
}