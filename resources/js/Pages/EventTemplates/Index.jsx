import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

function RaidBadge({ raid }) {
    return <span className="rounded bg-amber-600/20 px-2 py-0.5 text-xs text-amber-400">{raid.name}</span>;
}

function TemplateCard({ template, onDeleteClick }) {
    const updatedAt = new Date(template.updated_at).toLocaleDateString(undefined, {
        day: "numeric",
        month: "short",
        year: "numeric",
    });

    return (
        <div className="flex flex-col gap-3 rounded border border-amber-600/40 bg-brown-800/40 p-4">
            <div className="flex items-start justify-between gap-2">
                <h3 className="font-semibold text-white">{template.title}</h3>
                <span className="shrink-0 text-xs text-gray-500">Updated {updatedAt}</span>
            </div>
            <div className="flex flex-wrap gap-1">
                {template.raids.map((raid) => (
                    <RaidBadge key={raid.id} raid={raid} />
                ))}
            </div>
            <div className="flex gap-2">
                <Link
                    href={route("dashboard.event-templates.edit", template.id)}
                    className="flex items-center gap-1 rounded border border-amber-600 px-3 py-1 text-sm text-amber-400 transition-colors hover:bg-amber-600/20"
                >
                    <Icon icon="edit" style="light" />
                    Edit
                </Link>
                <button
                    type="button"
                    onClick={() => onDeleteClick(template)}
                    className="flex items-center gap-1 rounded border border-red-600/60 px-3 py-1 text-sm text-red-400 transition-colors hover:bg-red-600/20"
                >
                    <Icon icon="trash" style="light" />
                    Delete
                </button>
            </div>
        </div>
    );
}

function DeleteModal({ template, onClose }) {
    const [deleting, setDeleting] = useState(false);

    function handleDelete() {
        setDeleting(true);
        router.delete(route("dashboard.event-templates.destroy", template.id), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <Modal show={!!template} onClose={onClose} maxWidth="md">
            <div className="p-6 text-white">
                <h2 className="mb-2 text-lg font-semibold">Delete template</h2>
                <p className="mb-6 text-gray-400">
                    Are you sure you want to delete{" "}
                    <span className="font-semibold text-white">&ldquo;{template?.title}&rdquo;</span>? This cannot be
                    undone.
                </p>
                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded border border-gray-600 px-4 py-2 text-sm text-gray-300 transition-colors hover:bg-gray-600/20"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleDelete}
                        disabled={deleting}
                        className="rounded bg-red-700 px-4 py-2 text-sm text-white transition-colors hover:bg-red-600 disabled:opacity-50"
                    >
                        {deleting ? "Deleting…" : "Delete"}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

export default function Index({ templates, raidGroups }) {
    const [templateToDelete, setTemplateToDelete] = useState(null);

    return (
        <Master title="Event Templates">
            <SharedHeader backgroundClass="bg-ssctk" title="Event Templates" />
            <ToolNav>
                <div className="flex-initial space-x-4">
                    <Link
                        href={route("dashboard.index")}
                        className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to officers' dashboard
                    </Link>
                </div>
            </ToolNav>

            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <div className="mb-8 flex items-center justify-between">
                        <p className="text-gray-400">Create and manage reusable raid event templates.</p>
                        <Link
                            href={route("dashboard.event-templates.create")}
                            className="flex items-center gap-2 rounded bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-500"
                        >
                            <Icon icon="plus" style="light" />
                            Create Template
                        </Link>
                    </div>

                    {raidGroups.length === 0 ? (
                        <p className="text-center text-gray-500">No templates yet. Create one to get started.</p>
                    ) : (
                        <div className="flex flex-col gap-12">
                            {raidGroups.map(({ raid, templates: raidTemplates }) => (
                                <section key={raid.id}>
                                    <h2 className="mb-4 text-xl font-semibold text-amber-400">{raid.name}</h2>
                                    {raidTemplates.length === 0 ? (
                                        <p className="text-sm text-gray-500">No templates for this raid yet.</p>
                                    ) : (
                                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                            {raidTemplates.map((template) => (
                                                <TemplateCard
                                                    key={`${raid.id}-${template.id}`}
                                                    template={template}
                                                    onDeleteClick={setTemplateToDelete}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </section>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <DeleteModal template={templateToDelete} onClose={() => setTemplateToDelete(null)} />
        </Master>
    );
}
