import { useState, useRef } from "react";
import { useForm } from "@inertiajs/react";
import FormatButton from "@/Components/FormatButton";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";

const formatButtons = [
    { label: <Icon icon="bold" style="solid" />, title: "Bold", prefix: "**", suffix: "**", placeholder: "bold" },
    {
        label: <Icon icon="italic" style="solid" />,
        title: "Italic",
        prefix: "*",
        suffix: "*",
        placeholder: "italic",
        className: "italic",
    },
    {
        label: <Icon icon="list-ul" style="solid" />,
        title: "Bullet List",
        prefix: "- ",
        suffix: "",
        placeholder: "list item",
    },
    {
        label: <Icon icon="list-ol" style="solid" />,
        title: "Numbered List",
        prefix: "1. ",
        suffix: "",
        placeholder: "list item",
    },
    {
        label: <img src="/images/logo_wowhead_white.webp" alt="Wowhead Link" className="h-4 w-4" />,
        title: "Wowhead Link",
        prefix: "!wh[",
        suffix: "](item=12345)",
        placeholder: "Item Name",
    },
];

function validateCommentMarkdown(text) {
    // Comments: allow bold, italics, lists, line breaks, wowhead links
    // Do NOT allow underline or regular markdown links

    // Check for underline (++text++)
    if (/\+\+.+?\+\+/.test(text)) {
        return "Underline formatting is not allowed in comments.";
    }

    // Check for regular markdown links [text](url) but allow wowhead links !wh[text](item=123)
    // First, temporarily remove wowhead links, then check for remaining markdown links
    const withoutWowhead = text.replace(/!wh\[.+?\]\((item|spell)=\d+\)/g, "");
    if (/\[.+?\]\(.+?\)/.test(withoutWowhead)) {
        return "Regular links are not allowed. Use Wowhead format: !wh[Item Name](item=12345)";
    }

    return null;
}

export default function CommentForm({ itemId }) {
    const textareaRef = useRef(null);
    const [validationError, setValidationError] = useState(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        body: "",
    });

    function applyFormat(format) {
        const textarea = textareaRef.current;
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = data.body;
        const selectedText = text.substring(start, end);

        let newText;
        let newCursorPos;

        if (selectedText) {
            newText = text.substring(0, start) + format.prefix + selectedText + format.suffix + text.substring(end);
            newCursorPos = start + format.prefix.length + selectedText.length + format.suffix.length;
        } else {
            newText =
                text.substring(0, start) + format.prefix + format.placeholder + format.suffix + text.substring(end);
            newCursorPos = start + format.prefix.length;
        }

        setValidationError(validateCommentMarkdown(newText));
        setData("body", newText);

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
        setValidationError(validateCommentMarkdown(value));
        setData("body", value);
    }

    function submit(e) {
        e.preventDefault();
        const error = validateCommentMarkdown(data.body);
        if (error) {
            setValidationError(error);
            return;
        }
        post(route("loot.items.comments.store", { item: itemId }), {
            preserveScroll: true,
            onSuccess: () => {
                reset("body");
                setValidationError(null);
            },
        });
    }

    return (
        <form onSubmit={submit}>
            <div className="mb-2 flex items-center gap-1">
                {formatButtons.map((format) => (
                    <FormatButton
                        key={format.title}
                        title={format.title}
                        onClick={() => applyFormat(format)}
                        label={format.label}
                    />
                ))}
            </div>
            <div className="mb-4">
                <textarea
                    ref={textareaRef}
                    value={data.body}
                    onChange={handleChange}
                    placeholder="Supports **bold**, *italic*, - lists, 1. numbered, !wh[Item](item=12345)"
                    rows={4}
                    className="w-full rounded-md border-brown-600 bg-brown-800 text-white placeholder-gray-400 focus:border-primary focus:ring-primary"
                />
                <InputError message={validationError || errors.body} className="mt-2" />
            </div>
            <button
                type="submit"
                disabled={processing || validationError}
                className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800 ${
                    (processing || validationError) && "opacity-25"
                }`}
            >
                <Icon icon="paper-plane" style="solid" className="mr-1" />
                {processing ? "Posting..." : "Post Comment"}
            </button>
        </form>
    );
}
