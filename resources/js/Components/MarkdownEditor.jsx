import { useState, useRef, useEffect, useCallback } from "react";
import FormatButton from "@/Components/FormatButton";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";

/**
 * All available format button configurations
 */
const ALL_FORMATS = {
    bold: {
        label: <Icon icon="bold" style="solid" />,
        title: "Bold",
        prefix: "**",
        suffix: "**",
        placeholder: "bold",
        hint: "**bold**",
    },
    italic: {
        label: <Icon icon="italic" style="solid" />,
        title: "Italic",
        prefix: "*",
        suffix: "*",
        placeholder: "italic",
        hint: "*italic*",
    },
    underline: {
        label: <Icon icon="underline" style="solid" />,
        title: "Underline",
        prefix: "__",
        suffix: "__",
        placeholder: "underline",
        hint: "__underline__",
    },
    bulletList: {
        label: <Icon icon="list-ul" style="solid" />,
        title: "Bullet List",
        prefix: "- ",
        suffix: "",
        placeholder: "list item",
        hint: "- lists",
    },
    numberedList: {
        label: <Icon icon="list-ol" style="solid" />,
        title: "Numbered List",
        prefix: "1. ",
        suffix: "",
        placeholder: "list item",
        hint: "1. numbered",
    },
    link: {
        label: <Icon icon="link" style="solid" />,
        title: "Link",
        prefix: "[",
        suffix: "](url)",
        placeholder: "link text",
        hint: "[links](url)",
    },
    wowheadLink: {
        label: <img src="/images/logo_wowhead_white.webp" alt="Wowhead Link" className="h-4 w-4" />,
        title: "Wowhead Link",
        prefix: "!wh[",
        suffix: "](item=12345)",
        placeholder: "Item Name",
        hint: "!wh[Item](item=12345)",
    },
};

/**
 * All available validation rules
 */
const VALIDATION_RULES = {
    noUnderline: {
        pattern: /\+\+.+?\+\+/,
        message: "Underline formatting is not allowed.",
    },
    noRegularLinks: {
        // Check for regular markdown links but allow wowhead links
        validate: (text) => {
            const withoutWowhead = text.replace(/!wh\[.+?\]\((item|spell)=\d+\)/g, "");
            return /\[.+?\]\(.+?\)/.test(withoutWowhead);
        },
        message: "Regular links are not allowed. Use Wowhead format: !wh[Item Name](item=12345)",
    },
    noLineBreaks: {
        pattern: /[\r\n]/,
        message: "Line breaks are not allowed.",
    },
};

/**
 * Parses a Wowhead URL and extracts the type, ID, and name
 * @param {string} url - The Wowhead URL to parse
 * @returns {{ type: string, id: string, name: string } | null} - Parsed data or null if invalid
 */
function parseWowheadUrl(url) {
    // Match URLs like:
    // https://www.wowhead.com/tbc/item=28438/dragonmaw
    // https://www.wowhead.com/tbc/spell=34026/kill-command
    // https://www.wowhead.com/item=12345/some-item
    const match = url.match(/wowhead\.com\/(?:[^/]+\/)?(item|spell)=(\d+)(?:\/([a-z0-9-]+))?/i);
    if (!match) return null;

    const [, type, id, slug] = match;
    // Convert slug to display name: "kill-command" -> "Kill Command"
    const name = slug
        ? slug
              .split("-")
              .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
              .join(" ")
        : type.charAt(0).toUpperCase() + type.slice(1);

    return { type: type.toLowerCase(), id, name };
}

/**
 * Validates text against the specified validation rules
 * @param {string} text - The text to validate
 * @param {string[]} ruleKeys - Array of validation rule keys to apply
 * @returns {string|null} - Error message or null if valid
 */
function validateMarkdown(text, ruleKeys) {
    for (const key of ruleKeys) {
        const rule = VALIDATION_RULES[key];
        if (!rule) continue;

        if (rule.validate) {
            if (rule.validate(text)) {
                return rule.message;
            }
        } else if (rule.pattern) {
            if (rule.pattern.test(text)) {
                return rule.message;
            }
        }
    }
    return null;
}

/**
 * Generates a placeholder string based on allowed formats
 * @param {string[]} formatKeys - Array of format keys
 * @returns {string} - Placeholder text
 */
function generatePlaceholder(formatKeys) {
    const hints = formatKeys.map((key) => ALL_FORMATS[key]?.hint).filter(Boolean);
    if (hints.length === 0) return "";
    return "Supports " + hints.join(", ");
}

/**
 * A reusable markdown editor component with formatting buttons and validation
 *
 * @param {Object} props
 * @param {string} props.value - The current text value
 * @param {function} props.onChange - Callback when value changes (receives new value)
 * @param {string[]} [props.allowedFormats] - Array of format keys to enable (default: all formats)
 * @param {string[]} [props.validationRules] - Array of validation rule keys to apply (default: none)
 * @param {number} [props.rows] - Number of textarea rows (default: 4)
 * @param {string} [props.placeholder] - Custom placeholder text (auto-generated if not provided)
 * @param {string} [props.error] - External error message to display
 * @param {function} [props.onValidationChange] - Callback when validation state changes (receives error or null)
 * @param {string} [props.className] - Additional className for the container
 */
export default function MarkdownEditor({
    value,
    onChange,
    allowedFormats = Object.keys(ALL_FORMATS),
    validationRules = [],
    rows = 4,
    placeholder,
    error,
    onValidationChange,
    className = "",
}) {
    const textareaRef = useRef(null);
    const [validationError, setValidationError] = useState(null);
    const [showWowheadModal, setShowWowheadModal] = useState(false);
    const [wowheadUrl, setWowheadUrl] = useState("");
    const [wowheadError, setWowheadError] = useState(null);
    const [showLinkModal, setShowLinkModal] = useState(false);
    const [linkUrl, setLinkUrl] = useState("");
    const [linkText, setLinkText] = useState("");
    const [linkError, setLinkError] = useState(null);
    const [linkSelectionRange, setLinkSelectionRange] = useState({ start: 0, end: 0 });

    // Get the format configurations for allowed formats
    const formatButtons = allowedFormats.map((key) => ({ key, ...ALL_FORMATS[key] })).filter((f) => f.title);

    // Generate placeholder if not provided
    const computedPlaceholder = placeholder ?? generatePlaceholder(allowedFormats);

    // Validate function
    const validate = useCallback(
        (text) => {
            return validateMarkdown(text, validationRules);
        },
        [validationRules],
    );

    // Notify parent of validation changes
    useEffect(() => {
        onValidationChange?.(validationError);
    }, [validationError, onValidationChange]);

    function applyFormat(format) {
        // Special handling for wowheadLink - show modal instead
        if (format.key === "wowheadLink") {
            setWowheadUrl("");
            setWowheadError(null);
            setShowWowheadModal(true);
            return;
        }

        // Special handling for link - show modal instead
        if (format.key === "link") {
            const textarea = textareaRef.current;
            const start = textarea?.selectionStart ?? 0;
            const end = textarea?.selectionEnd ?? 0;
            const selectedText = textarea ? value.substring(start, end) : "";
            setLinkUrl("");
            setLinkText(selectedText);
            setLinkError(null);
            setLinkSelectionRange({ start, end });
            setShowLinkModal(true);
            return;
        }

        const textarea = textareaRef.current;
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = value;
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

        const validationResult = validate(newText);
        setValidationError(validationResult);
        onChange(newText);

        setTimeout(() => {
            textarea.focus();
            if (selectedText) {
                textarea.setSelectionRange(newCursorPos, newCursorPos);
            } else {
                textarea.setSelectionRange(newCursorPos, newCursorPos + format.placeholder.length);
            }
        }, 0);
    }

    function handleWowheadConfirm() {
        const parsed = parseWowheadUrl(wowheadUrl);
        if (!parsed) {
            setWowheadError(
                "Invalid Wowhead URL. Please use a URL like: https://www.wowhead.com/tbc/item=28438/dragonmaw",
            );
            return;
        }

        const textarea = textareaRef.current;
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = value;

        const formattedLink = `!wh[${parsed.name}](${parsed.type}=${parsed.id})`;
        const newText = text.substring(0, start) + formattedLink + text.substring(end);

        const validationResult = validate(newText);
        setValidationError(validationResult);
        onChange(newText);

        setShowWowheadModal(false);
        setWowheadUrl("");
        setWowheadError(null);

        setTimeout(() => {
            textarea.focus();
            const newCursorPos = start + formattedLink.length;
            textarea.setSelectionRange(newCursorPos, newCursorPos);
        }, 0);
    }

    function handleWowheadCancel() {
        setShowWowheadModal(false);
        setWowheadUrl("");
        setWowheadError(null);
    }

    function handleLinkConfirm() {
        // Validate https:// protocol
        if (!linkUrl.match(/^https:\/\/.+/i)) {
            setLinkError("Invalid URL. Please use a URL starting with https://");
            return;
        }

        const textarea = textareaRef.current;
        if (!textarea) return;

        const { start, end } = linkSelectionRange;
        const text = value;

        const displayText = linkText.trim() || linkUrl;
        const formattedLink = `[${displayText}](${linkUrl})`;
        const newText = text.substring(0, start) + formattedLink + text.substring(end);

        const validationResult = validate(newText);
        setValidationError(validationResult);
        onChange(newText);

        setShowLinkModal(false);
        setLinkUrl("");
        setLinkText("");
        setLinkError(null);

        setTimeout(() => {
            textarea.focus();
            const newCursorPos = start + formattedLink.length;
            textarea.setSelectionRange(newCursorPos, newCursorPos);
        }, 0);
    }

    function handleLinkCancel() {
        setShowLinkModal(false);
        setLinkUrl("");
        setLinkText("");
        setLinkError(null);
    }

    function handleChange(e) {
        const newValue = e.target.value;
        const validationResult = validate(newValue);
        setValidationError(validationResult);
        onChange(newValue);
    }

    return (
        <div className={className}>
            {formatButtons.length > 0 && (
                <div className="mb-2 flex items-center gap-1">
                    {formatButtons.map((format) => (
                        <FormatButton
                            key={format.key}
                            title={format.title}
                            onClick={() => applyFormat(format)}
                            label={format.label}
                        />
                    ))}
                </div>
            )}
            <div>
                <textarea
                    ref={textareaRef}
                    value={value}
                    onChange={handleChange}
                    placeholder={computedPlaceholder}
                    rows={rows}
                    className="w-full rounded-md border-brown-600 bg-brown-800 text-white placeholder-gray-400 focus:border-primary focus:ring-primary"
                />
                <InputError message={validationError || error} className="mt-2" />
            </div>

            <Modal show={showWowheadModal} onClose={handleWowheadCancel} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-medium text-white">Insert Wowhead Link</h2>
                    <p className="mt-1 text-sm text-gray-400">Paste a Wowhead URL for an item or spell.</p>

                    <div className="mt-4">
                        <input
                            type="text"
                            value={wowheadUrl}
                            onChange={(e) => {
                                setWowheadUrl(e.target.value);
                                setWowheadError(null);
                            }}
                            placeholder="https://www.wowhead.com/tbc/item=28438/dragonmaw"
                            className="w-full rounded-md border-brown-600 bg-brown-800 text-white placeholder-gray-400 focus:border-primary focus:ring-primary"
                            autoFocus
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                    e.preventDefault();
                                    handleWowheadConfirm();
                                }
                            }}
                        />
                        <InputError message={wowheadError} className="mt-2" />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={handleWowheadCancel}>Cancel</SecondaryButton>
                        <PrimaryButton onClick={handleWowheadConfirm}>Insert Link</PrimaryButton>
                    </div>
                </div>
            </Modal>

            <Modal show={showLinkModal} onClose={handleLinkCancel} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-medium text-white">Insert Link</h2>
                    <p className="mt-1 text-sm text-gray-400">Enter a URL and optional display text.</p>

                    <div className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="link-url" className="block text-sm font-medium text-gray-300">
                                URL
                            </label>
                            <input
                                id="link-url"
                                type="text"
                                value={linkUrl}
                                onChange={(e) => {
                                    setLinkUrl(e.target.value);
                                    setLinkError(null);
                                }}
                                placeholder="https://example.com"
                                className="mt-1 w-full rounded-md border-brown-600 bg-brown-800 text-white placeholder-gray-400 focus:border-primary focus:ring-primary"
                                autoFocus
                            />
                            <InputError message={linkError} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="link-text" className="block text-sm font-medium text-gray-300">
                                Display Text <span className="text-gray-500">(optional)</span>
                            </label>
                            <input
                                id="link-text"
                                type="text"
                                value={linkText}
                                onChange={(e) => setLinkText(e.target.value)}
                                placeholder="Click here"
                                className="mt-1 w-full rounded-md border-brown-600 bg-brown-800 text-white placeholder-gray-400 focus:border-primary focus:ring-primary"
                                onKeyDown={(e) => {
                                    if (e.key === "Enter") {
                                        e.preventDefault();
                                        handleLinkConfirm();
                                    }
                                }}
                            />
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={handleLinkCancel}>Cancel</SecondaryButton>
                        <PrimaryButton onClick={handleLinkConfirm}>Insert Link</PrimaryButton>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

// Export constants for consumers to reference
export { ALL_FORMATS, VALIDATION_RULES };
