import { useState, useRef, useEffect } from "react";
import { useForm, Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import MarkdownEditor from "@/Components/MarkdownEditor";
import ImageManager from "@/Components/ImageManager";
import Icon from "@/Components/FontAwesome/Icon";

function SaveIndicator({ saving, saved }) {
    const visible = saving || saved;
    const color = saving ? "text-amber-400" : "text-green-400";
    return (
        <span
            className={`flex items-center gap-1.5 text-sm font-medium transition-opacity duration-200 ${color} ${visible ? "opacity-100" : "pointer-events-none opacity-0"}`}
        >
            <span className={saving ? "" : "hidden"}>
                <Icon icon="spinner" style="solid" className="fa-spin" />
            </span>
            <span className={saving ? "hidden" : ""}>
                <Icon icon="check" style="solid" />
            </span>
            {saving ? "Saving…" : "Saved"}
        </span>
    );
}

export default function EditBossStrategy({ boss }) {
    boss = boss.data ?? boss ?? {};

    const updateRoute = route("dashboard.boss-strategies.update", { boss: boss.id });

    const [imageOrder, setImageOrder] = useState(boss.images || []);
    const [showNotesSaved, setShowNotesSaved] = useState(false);
    const [showImagesSaved, setShowImagesSaved] = useState(false);
    const [imagesSaving, setImagesSaving] = useState(false);
    const isFirstRender = useRef(true);
    const debounceTimer = useRef(null);
    const heartbeatInterval = useRef(null);

    const { data, setData, patch, processing, errors, isDirty } = useForm({
        notes: boss.notes ?? "",
    });

    const flashImagesSaved = () => {
        setShowImagesSaved(true);
        setTimeout(() => setShowImagesSaved(false), 2000);
    };

    const flashNotesSaved = () => {
        setShowNotesSaved(true);
        setTimeout(() => setShowNotesSaved(false), 2000);
    };

    const saveNotes = () => {
        patch(updateRoute, {
            preserveScroll: true,
            only: ["boss"],
            onSuccess: flashNotesSaved,
        });
    };

    // Debounced auto-save: 5 s after notes stop changing
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }
        if (!isDirty) return;

        debounceTimer.current = setTimeout(saveNotes, 5000);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [data.notes]);

    // Heartbeat: save every 30 s if dirty
    useEffect(() => {
        heartbeatInterval.current = setInterval(() => {
            if (isDirty) {
                saveNotes();
            }
        }, 30000);

        return () => {
            if (heartbeatInterval.current) {
                clearInterval(heartbeatInterval.current);
            }
        };
    }, [isDirty, data.notes]);

    const patchImages = (imageData) => {
        setImagesSaving(true);
        router.patch(updateRoute, imageData, {
            preserveScroll: true,
            only: ["boss"],
            forceFormData: true,
            onSuccess: (page) => {
                setImagesSaving(false);
                flashImagesSaved();
                const freshBoss = page.props.boss?.data ?? page.props.boss ?? {};
                setImageOrder(freshBoss.images || []);
            },
            onError: () => setImagesSaving(false),
        });
    };

    const handleUploadFiles = (files) => {
        if (!files.length) return;
        const previewUrls = files.map((f) => URL.createObjectURL(f));
        setImageOrder((prev) => [...prev, ...previewUrls]);

        router.patch(
            updateRoute,
            { images: files },
            {
                preserveScroll: true,
                only: ["boss"],
                forceFormData: true,
                onSuccess: (page) => {
                    const freshBoss = page.props.boss?.data ?? page.props.boss ?? {};
                    setImageOrder(freshBoss.images || []);
                    flashImagesSaved();
                },
            },
        );
    };

    const handleDeleteImage = (url) => {
        setImageOrder((prev) => prev.filter((u) => u !== url));
        patchImages({ deleted_images: [url] });
    };

    const handleReorderImages = (newOrder) => {
        setImageOrder(newOrder);
        patchImages({ image_order: newOrder });
    };

    return (
        <Master title={`Edit Strategy — ${boss.name}`}>
            <SharedHeader title={boss.name} backgroundClass={boss.raid.background ?? "bg-officer-meeting"} />
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto max-w-4xl px-4">
                    <div className="flex min-h-12 flex-col items-center justify-between md:flex-row">
                        <div className="flex-initial space-x-4">
                            <Link
                                href={route("dashboard.boss-strategies.index")}
                                className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                            >
                                <Icon icon="arrow-left" style="solid" className="mr-2" />
                                <span>Back to {boss.raid.name} strategies</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
            <div className="py-12 text-white">
                <div className="container mx-auto max-w-4xl space-y-8 px-4">
                    <p className="text-md mb-6">
                        Use the editor below to write strategy notes for this boss. You can also upload images to
                        illustrate the strategy, which will be displayed in the order you arrange them.
                    </p>
                    {/* Notes Section */}
                    <div>
                        <h3 className="not-sr-only flex items-center gap-3 text-lg font-bold">
                            Strategy Notes
                            <SaveIndicator saving={processing} saved={showNotesSaved} />
                        </h3>
                        <MarkdownEditor
                            value={data.notes}
                            onChange={(value) => setData("notes", value)}
                            rows={12}
                            error={errors.notes}
                            className="mt-2"
                        />
                    </div>

                    {/* Image Management Section */}
                    <div>
                        <h3 className="mb-2 flex items-center gap-3 text-lg font-bold">
                            Strategy Images
                            <SaveIndicator saving={imagesSaving} saved={showImagesSaved} />
                        </h3>
                        <ImageManager
                            images={imageOrder}
                            saving={imagesSaving}
                            error={errors.images}
                            onUpload={handleUploadFiles}
                            onDelete={handleDeleteImage}
                            onReorder={handleReorderImages}
                        />
                    </div>
                </div>
            </div>
        </Master>
    );
}
