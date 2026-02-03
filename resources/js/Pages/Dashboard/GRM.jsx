import { useState } from 'react';
import { Deferred, useForm } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import SharedHeader from '@/Components/SharedHeader';
import Master from '@/Layouts/Master';
import InputError from '@/Components/InputError';
import '@/../css/FrizQuadrata.css';

export default function GRM({ lastUploadTimestamp, memberCount }) {
    const [isDragging, setIsDragging] = useState(false);

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        grm_data: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('dashboard.grm-upload.upload'));
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const files = e.dataTransfer.files;
        if (files.length === 0) return;

        const file = files[0];
        if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const content = event.target.result;
            setData('grm_data', data.grm_data ? data.grm_data + '\n' + content : content);
        };
        reader.readAsText(file);
    };

    return (
        <Master title="GRM Data Upload">
            <SharedHeader backgroundClass="bg-officer-meeting" title="GRM Data Upload" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <p className="text-xl font-bold mb-6">Upload your GRM data here.</p>
                    {lastUploadTimestamp ? (
                        <p className="text-md text-gray-400 mb-6">The last GRM data upload was made on {lastUploadTimestamp}</p>
                    ) : (
                        <p className="text-md text-gray-400 mb-6">No previous uploads found.</p>
                    )}
                    <p className="text-lg mb-6">To export your GRM data, follow these steps:</p>
                    <ol className="list-decimal list-inside mb-6 space-y-2">
                        <li>Open <span className="inline-block p-1 bg-brown-800 border border-amber-800 rounded-sm font-bold font-mono">/grm export</span> in-game.</li>
                        <li>Select the <strong>Members</strong> tab.</li>
                        <li>Set the <strong>delimiter</strong> to a comma (<span className="text-2xl font-bold font-mono">,</span>)</li>
                        <li>
                            Make sure the right columns are selected for export. You need to select the following columns:
                            <ul className="list-disc list-inside ml-6 my-1">
                                <li>Name</li>
                                <li>Rank</li>
                                <li>Level</li>
                                <li>Last Online</li>
                                <li>Main/Alt</li>
                                <li>Player Alts</li>
                            </ul>
                            <p className="text-gray-400 italics mt-1">Any other columns are optional, but ideally you should only select the ones listed above.</p>
                        </li>
                        <li>Make sure <strong>Remove Alt-Code Letters From Names</strong> is <span className="font-bold underline uppercase">not</span> checked.</li>
                        <li>Make sure <strong>Auto Include Headers</strong> <span className="font-bold underline uppercase">is</span> checked.</li>
                        <li>Click the
                            <span className="inline-block bg-red-600 font-bold font-friz-quadrata text-[#ffff00] border border-gray-600 rounded-md shadow-md mx-1 px-6 py-2">
                                Export Selection
                            </span> button.
                        </li>
                        <li>Copy the exported CSV data, and paste it below.</li>
                        <li>Click the
                            <span className="inline-block bg-red-600 font-bold font-friz-quadrata text-[#ffff00] border border-gray-600 rounded-md shadow-md mx-1 px-6 py-2">
                                Export Next <Deferred data="memberCount" fallback={<span className="italics">X</span>}>{memberCount-500}</Deferred>
                            </span>
                            button, copy the new data, and paste it below, appending it to the previous data.
                        </li>
                    </ol>

                    <form onSubmit={handleSubmit}>
                        <textarea
                            name="grm_data"
                            rows="10"
                            className={`w-full p-4 mb-2 bg-brown-800 border rounded text-white transition-colors ${
                                isDragging
                                    ? 'border-blue-500 bg-brown-700'
                                    : errors.grm_data
                                        ? 'border-red-500'
                                        : 'border-brown-700'
                            }`}
                            placeholder="Paste your GRM CSV data here, or drag and drop a CSV file."
                            value={data.grm_data}
                            onChange={(e) => setData('grm_data', e.target.value)}
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onDrop={handleDrop}
                        />
                        <InputError message={errors.grm_data} className="mb-4" />

                        <div className="flex items-center gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded"
                            >
                                {processing ? 'Uploading...' : 'Upload GRM Data'}
                            </button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-green-400">GRM data uploaded successfully.</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </div>
        </Master>
    );
}