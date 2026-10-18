import { modelBackground } from "@/Helpers/ModelColor";

/**
 * The page masthead. A model's `image` and `color` (such as a game version's
 * or raid's banner and colour) replace the default background: the image sits
 * over the colour, so the colour shows while it loads or when there's no
 * image. `imagePosition` is a CSS background position for the image.
 */
export default function SharedHeader({
    title,
    subtitle,
    backgroundClass = "bg-masthead",
    color,
    image,
    imagePosition,
}) {
    const { className, style } = modelBackground(color, image, imagePosition, backgroundClass);

    return (
        <header
            className={`${className} h-64 bg-cover bg-center text-white [mask-image:linear-gradient(to_bottom,#000_90%,transparent)] md:h-128`}
            style={style}
        >
            <div className="flex h-full w-full items-center bg-black/50 px-4 pt-20.5 pb-8 lg:pt-21.5">
                <div className="container mx-auto">
                    <h1 className="from-amber-100 to-amber-300 mb-3 bg-linear-to-b bg-clip-text pb-1 text-center font-serif text-5xl text-transparent drop-shadow-[0_2px_12px_rgba(0,0,0,0.8)] text-shadow-sm">
                        {title}
                        {subtitle && (
                            <span className="from-amber-300 to-amber-500 text-amber-400 mt-2 block bg-linear-to-b bg-clip-text text-xl">
                                {subtitle}
                            </span>
                        )}
                    </h1>
                </div>
            </div>
        </header>
    );
}
