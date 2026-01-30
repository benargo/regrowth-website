import { useState, useRef, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import FormatButton from '@/Components/FormatButton';
import FormattedMarkdown from '@/Components/FormattedMarkdown';
import InputError from '@/Components/InputError';

const formatButtons = [
    { label: (<i className="fas fa-bold"></i>), title: 'Bold', prefix: '**', suffix: '**', placeholder: 'bold' },
    { label: (<i className="fas fa-italic"></i>), title: 'Italic', prefix: '*', suffix: '*', placeholder: 'italic', className: 'italic' },
    { label: (<i className="fas fa-underline"></i>), title: 'Underline', prefix: '__', suffix: '__', placeholder: 'underline', className: 'underline' },
    { label: (<i className="fas fa-link"></i>), title: 'Link', prefix: '[', suffix: '](url)', placeholder: 'link text' },
    { label: (<img src="/images/logo_wowhead_white.webp" alt="Wowhead Link" className="w-4 h-4"/>), title: 'Wowhead Link', prefix: '!wh[', suffix: '](item=12345)', placeholder: 'Item Name' },
];

function validateNotesMarkdown(text) {
    // Notes: allow bold, italics, underline, lists, links, wowhead links
    // Do NOT allow line breaks
    if (/[\r\n]/.test(text)) {
        return 'Line breaks are not allowed in notes.';
    }
    return null;
}

export default function Notes({ notes, itemId, canEdit }) {
    if (!notes && !canEdit) {
        return null;
    }

    const textareaRef = useRef(null);
    const [validationError, setValidationError] = useState(null);
    const { data, setData, post, processing, errors, reset, setDefaults } = useForm({
        notes: notes || '',
    });

    // Sync form data when notes prop changes (e.g., after save and redirect)
    useEffect(() => {
        setDefaults({ notes: notes || '' });
        setData('notes', notes || '');
    }, [notes]);

    function applyFormat(format) {
        const textarea = textareaRef.current;
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = data.notes;
        const selectedText = text.substring(start, end);

        let newText;
        let newCursorPos;

        if (selectedText) {
            newText = text.substring(0, start) + format.prefix + selectedText + format.suffix + text.substring(end);
            newCursorPos = start + format.prefix.length + selectedText.length + format.suffix.length;
        } else {
            newText = text.substring(0, start) + format.prefix + format.placeholder + format.suffix + text.substring(end);
            newCursorPos = start + format.prefix.length;
        }

        setValidationError(validateNotesMarkdown(newText));
        setData('notes', newText);

        setTimeout(() => {
            textarea.focus();
            if (selectedText) {
                textarea.setSelectionRange(newCursorPos, newCursorPos);
            } else {
                textarea.setSelectionRange(newCursorPos, newCursorPos + format.placeholder.length);
            }
        }, 0);
    }

    function handleChange(e) {
        const value = e.target.value;
        setValidationError(validateNotesMarkdown(value));
        setData('notes', value);
    }

    function submit(e) {
        e.preventDefault();
        const error = validateNotesMarkdown(data.notes);
        if (error) {
            setValidationError(error);
            return;
        }
        post(route('loot.items.notes.store', { item: itemId }), {
            preserveScroll: true,
            onSuccess: () => {
                setValidationError(null);
            },
        });
    }

    if (canEdit) {
        return (
            <form onSubmit={submit} className="mt-8">
                <h2 className="text-xl font-bold mb-2">Officers&rsquo; notes</h2>
                <p className="text-md text-gray-400 mb-4">Notes are unique to each loot item. If you change what another officer has written, it will overwrite their notes.</p>
                <div className="flex flex-col-reverse md:flex-row justify-between gap-4 mb-4">      
                    {/* Formatting Buttons */}
                    <div className="flex items-center gap-1">
                        {formatButtons.map((format) => (
                            <FormatButton
                                key={format.title}
                                title={format.title}
                                onClick={() => applyFormat(format)}
                                label={format.label}
                            />
                        ))}
                    </div>
                    {/* Actions */}
                    <div className="flex items-center justify-between gap-4">
                        <button
                            onClick={() => reset('notes')}
                            disabled={processing}
                            className={`inline-flex items-center rounded-md border border-transparent px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-brown-700 focus:bg-brown-700 focus:outline-none focus:ring-2 focus:ring-brown-500 focus:ring-offset-2 active:bg-brown-800 ${
                                processing && 'opacity-25'
                            }`}
                        >
                            <i className="fas fa-trash mr-1"></i> Reset notes
                        </button>
                        <button
                            type="submit"
                            disabled={processing || validationError}
                            className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800 ${
                                (processing || validationError) && 'opacity-25'
                            }`}
                        >
                            <i className="fas fa-save mr-1"></i> {processing ? 'Saving...' : 'Save'}
                        </button>
                    </div>
                </div>
                <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
                    <textarea
                        ref={textareaRef}
                        value={data.notes}
                        onChange={handleChange}
                        placeholder="Supports **bold**, *italic*, __underline__, [links](url), !wh[Item](item=12345)"
                        rows={2}
                        className="w-full rounded-md border-brown-600 bg-brown-800 text-white placeholder-gray-400 focus:border-primary focus:ring-primary"
                    />
                </div>
                <InputError message={validationError || errors.notes} className="mt-2" />
            </form>
        );
    }

    return (
        <div className="mt-8">
            <h2 className="text-xl font-bold mb-6">Officers&rsquo; Notes</h2>
            <FormattedMarkdown>{notes}</FormattedMarkdown>
        </div>
    )
}