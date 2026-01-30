export default function LootPageHeader({ title }) {
    return (
        <div className="bg-karazhan py-24 text-white">
            <div className="container mx-auto px-4">
                <h1 className="text-4xl font-bold text-center">
                    {title}
                </h1>
            </div>
        </div>
    );
}
