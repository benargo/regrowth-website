function CommentItemSkeleton() {
    return (
        <div className="rounded-lg border border-brown-700 bg-brown-800 p-4">
            {/* Header skeleton */}
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="h-8 w-8 rounded-full bg-amber-600/30"></div>
                    <div className="h-4 w-24 rounded bg-amber-600/30"></div>
                </div>
                <div className="h-3 w-32 rounded bg-amber-600/20"></div>
            </div>

            {/* Body skeleton */}
            <div className="space-y-2">
                <div className="h-4 w-full rounded bg-amber-600/20"></div>
                <div className="h-4 w-3/4 rounded bg-amber-600/20"></div>
                <div className="h-4 w-1/2 rounded bg-amber-600/20"></div>
            </div>
        </div>
    );
}

export default function CommentsSkeleton() {
    return (
        <section className="mt-12 w-full animate-pulse">
            <h2 className="mb-6 text-xl font-bold">Discussion</h2>

            {/* Form skeleton */}
            <div className="mb-8">
                <div className="h-32 rounded-lg border border-brown-700 bg-brown-800/50"></div>
            </div>

            {/* Comments list skeleton */}
            <div className="space-y-4">
                <CommentItemSkeleton />
                <CommentItemSkeleton />
                <CommentItemSkeleton />
            </div>
        </section>
    );
}
