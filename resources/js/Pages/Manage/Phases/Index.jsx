import { useForm } from "@inertiajs/react";
import { useState } from "react";
import Master from "@/Layouts/Master";
import Collapsible from "@/Components/Collapsible";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import SharedHeader from "@/Components/SharedHeader";
import TextInput from "@/Components/TextInput";
import PageContainer from "@/Components/PageContainer";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";

export default function ManagePhases({ phases, current_phase }) {
    const [editingPhase, setEditingPhase] = useState(null);

    const { data, setData, put, processing, errors, reset } = useForm({
        start_date: "",
    });

    const toParisDatetimeLocal = (isoString) => {
        if (!isoString) {
            return "";
        }
        const date = new Date(isoString);
        const parisDate = new Intl.DateTimeFormat("sv-SE", {
            timeZone: "Europe/Paris",
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false,
        }).format(date);
        return parisDate.replace(" ", "T");
    };

    const openEditModal = (phase) => {
        setEditingPhase(phase);
        setData("start_date", toParisDatetimeLocal(phase.start_date));
    };

    const closeModal = () => {
        setEditingPhase(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route("management.phases.update", editingPhase.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const formatDate = (dateString, options = {}) => {
        const date = new Date(dateString);
        return date.toLocaleDateString("en-GB", {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            timeZoneName: "short",
            ...options,
        });
    };

    const isLocalTimezoneDifferent = (dateString) => {
        const date = new Date(dateString);
        const localTime = date.toLocaleString("en-GB", { timeZone: undefined });
        const parisTime = date.toLocaleString("en-GB", { timeZone: "Europe/Paris" });
        return localTime !== parisTime;
    };

    return (
        <Master title="Manage TBC Phases">
            <SharedHeader title="Manage TBC Phases" backgroundClass="bg-officer-meeting" />
            <PageContainer>
                <div className="flex flex-col gap-4">
                {phases?.map((phase) => (
                    <Collapsible
                        key={phase.id}
                        title={`Phase ${phase.number}`}
                        style="amber"
                        headerRight={
                            phase.id === current_phase && (
                                <div className="rounded-md bg-green-600 px-2 py-1 text-xs font-semibold text-white">
                                    Current Phase
                                </div>
                            )
                        }
                    >
                        <div className="grid grid-cols-1 md:grid-cols-3">
                            {/* Start date */}
                            <div className="text-md my-4 md:mr-8">
                                <h3 className="text-lg font-bold">
                                    {phase.start_date && new Date(phase.start_date) < new Date()
                                        ? "Phase started on"
                                        : "Phase starts on"}
                                </h3>
                                {phase.start_date && (
                                    <p>
                                        <span className="font-bold">Server time:</span>&nbsp;
                                        {formatDate(phase.start_date, { timeZone: "Europe/Paris" })}
                                    </p>
                                )}
                                {phase.start_date && isLocalTimezoneDifferent(phase.start_date) && (
                                    <p>
                                        <span className="font-bold">Local time:</span>&nbsp;
                                        {formatDate(phase.start_date)}
                                    </p>
                                )}
                                {!phase.start_date && <p>a date yet to be determined.</p>}
                                <p className="flex justify-center md:justify-start">
                                    <button
                                        onClick={() => openEditModal(phase)}
                                        className="mt-2 flex items-center gap-4 rounded border border-ink-600 px-2 py-3 transition-colors hover:bg-ink-600/20"
                                    >
                                        <div className="mx-1 text-center">
                                            <Icon icon="edit" style="regular" className="h-4 w-4" />
                                        </div>
                                        <div className="text-md mr-1">Edit phase start date</div>
                                    </button>
                                </p>
                            </div>
                            {/* Raids */}
                            <div className="text-md my-4 md:mr-8">
                                <h3 className="text-lg font-bold">Raids in this phase</h3>
                                {Object.values(phase.raids).length > 0 ? (
                                    <ul>
                                        {Object.values(phase.raids).map((raid) => (
                                            <li key={raid.id} className="flex">
                                                {raid.name}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="flex">No raids assigned to this phase.</p>
                                )}
                            </div>
                            {/* Bosses */}
                            <div className="text-md my-4 md:mr-8">
                                <h3 className="text-lg font-bold">Bosses in this phase</h3>
                                {phase.raids.some((raid) => raid.bosses?.length > 0) ? (
                                    <ul>
                                        {phase.raids.flatMap((raid) => raid.bosses ?? []).map((boss) => (
                                            <li key={boss.id}>{boss.name}</li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mb-2">No bosses assigned to this phase.</p>
                                )}
                            </div>
                        </div>
                    </Collapsible>
                ))}
                </div>
            </PageContainer>

            {/* Edit Start Date Modal */}
            <Modal show={editingPhase !== null} onClose={closeModal} maxWidth="md">
                <form onSubmit={handleSubmit} className="p-6">
                    <h2 className="text-lg font-bold text-white">Edit Phase {editingPhase?.id} Start Date</h2>
                    <p className="mt-1 text-sm text-white">
                        Enter the start date and time in Europe/Paris timezone (server time).
                    </p>
                    <div className="mt-4">
                        <InputLabel htmlFor="start_date" value="Start Date (Europe/Paris)" className="text-secondary-400" />
                        <TextInput
                            id="start_date"
                            type="datetime-local"
                            value={data.start_date}
                            onChange={(e) => setData("start_date", e.target.value)}
                            className="bg-ground-800/50 mt-1 block w-full text-white"
                        />
                        <InputError message={errors.start_date} className="mt-2" />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={closeModal}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" processing={processing}>
                            {processing ? "Saving..." : "Save"}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </Master>
    );
}
