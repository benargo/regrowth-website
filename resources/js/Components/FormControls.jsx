import { useState } from "react";
import {
    Button,
    Checkbox as HeadlessCheckbox,
    Description,
    Field,
    Fieldset,
    Input,
    Label,
    Legend,
    Radio,
    RadioGroup,
    Select,
} from "@headlessui/react";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import cn from "@/Helpers/cn";
import slugify from "@/Helpers/Slugify";

export const controlClassName =
    "block w-full rounded border border-amber-600 bg-brown-800 px-4 py-2 text-white placeholder-gray-300 " +
    "data-focus:border-amber-400 data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-amber-400 " +
    "data-invalid:border-red-400";

const buttonLayoutClassName = "inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold";

const buttonBaseClassName = `${buttonLayoutClassName} data-disabled:opacity-50 data-focus:outline-2 data-focus:outline-offset-2`;

const buttonClassName = `bg-amber-800 data-hover:bg-amber-900 data-focus:outline-amber-400 text-white ${buttonBaseClassName}`;

/** A quieter button beside a primary one, such as Cancel or Clear. */
const secondaryButtonClassName =
    `bg-brown-800 text-gray-200 inset-ring inset-ring-amber-600 data-hover:bg-brown-700 data-hover:text-white ` +
    `data-focus:outline-amber-400 ${buttonBaseClassName}`;

/** A button for a destructive action, such as confirming a delete. */
const dangerButtonClassName = `bg-red-700 data-hover:bg-red-800 data-focus:outline-red-400 text-white ${buttonBaseClassName}`;

/** A Link styled like PrimaryButton, such as Edit or Finish. */
export const linkButtonClassName =
    `bg-amber-800 hover:bg-amber-900 focus-visible:outline-amber-400 text-white focus-visible:outline-2 ` +
    `focus-visible:outline-offset-2 ${buttonLayoutClassName}`;

/** A quiet text link beside a form's buttons, such as Cancel, Back or Skip. */
export const linkClassName =
    "text-gray-300 focus-visible:outline-amber-400 rounded px-2 py-2 text-sm underline hover:text-white " +
    "focus-visible:outline-2 focus-visible:outline-offset-2";

/**
 * A labelled HeadlessUI field. The hint and the error are both Descriptions,
 * so the control's aria-describedby announces them. With `hintAsTooltip`,
 * the hint is screen-reader-only and sighted users see it in a tooltip
 * beside the label instead.
 *
 * `htmlFor` is optional. Without it, the label points at a HeadlessUI
 * control inside the field that has no `id` of its own, such as a TextInput.
 * Pass `htmlFor`, matching the control's `id`, when the control sets an `id`
 * (OptionSelect, SlugInput and CheckboxInput default theirs to `name`) or is
 * not a HeadlessUI control, such as Autocomplete, DateFilterButton or Checkbox.
 */
export function FormRow({ htmlFor, label, required = false, hint, hintAsTooltip = false, error, className, children }) {
    const labelContent = (
        <>
            {label}
            {required && (
                <span aria-hidden="true" className="ml-1 text-red-300">
                    *
                </span>
            )}
        </>
    );

    return (
        <Field className={cn("flex flex-col gap-1.5", className)}>
            <Label htmlFor={htmlFor} className="text-sm font-medium text-gray-300">
                {hint && hintAsTooltip ? (
                    <Tooltip as="span" body={hint}>
                        {labelContent}
                        <Icon icon="question-circle" style="solid" className="ml-1.5" aria-hidden="true" />
                    </Tooltip>
                ) : (
                    labelContent
                )}
            </Label>
            {children}
            {hint && <Description className={hintAsTooltip ? "sr-only" : "text-sm text-gray-300"}>{hint}</Description>}
            {error && <Description className="text-sm text-red-300">{error}</Description>}
        </Field>
    );
}

/** Explains the asterisk FormRow puts on required fields. */
export function RequiredFieldsNote() {
    return (
        <p className="text-sm text-gray-300">
            Fields marked with{" "}
            <span aria-hidden="true" className="text-red-300">
                *
            </span>
            <span className="sr-only">an asterisk</span> are required.
        </p>
    );
}

/** A validation message shown outside a FormRow. Renders nothing without a message. */
export function FieldError({ message, id, className }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className={cn("text-sm text-red-300", className)}>
            {message}
        </p>
    );
}

/**
 * A HeadlessUI text input with the shared control styling. Other props,
 * including `ref`, `type`, `invalid` and `autoFocus`, pass through to the
 * input. `className` is merged over controlClassName.
 */
export function TextInput({ className, ...props }) {
    return <Input {...props} className={cn(controlClassName, className)} />;
}

const checkboxClassName =
    "size-4 shrink-0 cursor-pointer rounded border-amber-600 bg-brown-800 checked:bg-amber-600 indeterminate:bg-amber-600 " +
    "focus:ring-0 focus:ring-offset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 " +
    "aria-invalid:border-red-400 disabled:cursor-not-allowed disabled:opacity-50";

/**
 * A native checkbox with the shared styling, for use beside its own
 * <label>. `checked` and `onChange(event)` are the native ones; `ref`
 * reaches the input, so callers can set `indeterminate`. `className` is
 * merged over the shared styling.
 */
export function Checkbox({ className, ...props }) {
    return <input {...props} type="checkbox" className={cn(checkboxClassName, className)} />;
}

function ActionButton({ variantClassName, processing, disabled, className, children, ...props }) {
    return (
        <Button
            {...props}
            disabled={disabled || processing}
            aria-busy={processing || undefined}
            className={cn(variantClassName, className)}
        >
            {children}
        </Button>
    );
}

/**
 * The main action of a form or dialog. It is `type="button"` unless given
 * `type="submit"`, and it is disabled while `processing`.
 */
export function PrimaryButton(props) {
    return <ActionButton variantClassName={buttonClassName} {...props} />;
}

/** A secondary action, such as Cancel. Same props as PrimaryButton. */
export function SecondaryButton(props) {
    return <ActionButton variantClassName={secondaryButtonClassName} {...props} />;
}

/** A destructive action, such as Delete. Same props as PrimaryButton. */
export function DangerButton(props) {
    return <ActionButton variantClassName={dangerButtonClassName} {...props} />;
}

/**
 * A HeadlessUI select built from `options` (`[{ value, label }]`) and, when
 * given, `groups` (`[{ label, options }]`), each rendered as a headed
 * <optgroup> after the ungrouped options. The empty placeholder option
 * renders only when `placeholder` is given. onChange receives the chosen
 * value as a string. `className` is merged over controlClassName.
 */
export function OptionSelect({
    name,
    id = name,
    value,
    onChange,
    options = [],
    groups = null,
    placeholder,
    invalid,
    required = false,
    className,
    ...props
}) {
    return (
        <Select
            {...props}
            id={id}
            name={name}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            invalid={invalid}
            required={required}
            className={cn(controlClassName, className)}
        >
            {placeholder !== undefined && <option value="">{placeholder}</option>}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
            {groups?.map((group, index) => (
                <optgroup key={`${index}-${group.label}`} label={group.label}>
                    {group.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </optgroup>
            ))}
        </Select>
    );
}

/**
 * A HeadlessUI checkbox for use inside FormRow, whose Field labels it.
 * onChange receives the new checked state.
 */
export function CheckboxInput({ name, id = name, checked, onChange, invalid }) {
    return (
        <HeadlessCheckbox
            id={id}
            name={name}
            checked={checked}
            onChange={onChange}
            aria-invalid={invalid ? true : undefined}
            className={
                "group bg-brown-800 border-amber-600 data-checked:bg-amber-600 data-focus:outline-amber-400 " +
                "block size-10.5 rounded border p-2 aria-invalid:border-red-400 data-focus:outline-2 data-focus:outline-offset-2"
            }
        >
            <svg
                className="hidden size-full fill-none stroke-white group-data-checked:block"
                viewBox="0 0 14 14"
                aria-hidden="true"
            >
                <path d="M3 8L6 11L11 3.5" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            </svg>
        </HeadlessCheckbox>
    );
}

/**
 * A HeadlessUI radio group drawn as selectable cards, each with an optional
 * icon and description. `options` is `[{ value, label, description, icon }]`;
 * onChange receives the chosen value. Pass layout classes, such as a grid,
 * through `className`.
 */
export function RadioCardGroup({ id, name, value, onChange, options, invalid, className = "" }) {
    return (
        <RadioGroup
            id={id}
            name={name}
            value={value}
            onChange={onChange}
            aria-invalid={invalid ? true : undefined}
            className={className}
        >
            {options.map((option) => (
                <Field
                    key={option.value}
                    className={
                        "bg-brown-800 border-amber-600 has-data-checked:border-amber-300 has-data-checked:bg-amber-600/30 " +
                        "grid grid-cols-[1.125rem_1fr] has-data-focus:outline-amber-400 has-data-hover:border-amber-400 " +
                        "content-start items-center gap-x-3 gap-y-1 rounded border p-3 has-data-focus:outline-2 has-data-focus:outline-offset-2"
                    }
                >
                    <Radio
                        value={option.value}
                        className={
                            "group bg-brown-900 flex size-4.5 cursor-pointer border-amber-500 data-checked:border-sky-400 " +
                            "items-center justify-center rounded-full border focus:outline-none"
                        }
                    >
                        <span className="invisible size-2 rounded-full bg-sky-400 group-data-checked:visible" />
                    </Radio>
                    <Label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-white">
                        {option.icon && (
                            <Icon
                                icon={option.icon}
                                style="solid"
                                className="w-4 text-center text-sky-400"
                                aria-hidden="true"
                            />
                        )}
                        {option.label}
                    </Label>
                    {option.description && (
                        <Description className="col-start-2 text-sm text-gray-300">{option.description}</Description>
                    )}
                </Field>
            ))}
        </RadioGroup>
    );
}

/**
 * A text input that only ever holds a slug: "tHe Burning CruSade" shows as
 * "the-burning-crusade" as it's typed. The DOM value and caret are rewritten
 * in the change handler, before React renders, so the raw text never appears
 * and the caret stays put when editing mid-word.
 *
 * The value passed to onChange never starts or ends with a dash. The field
 * itself shows a trailing dash while typing, so the space before the next
 * word doesn't vanish, and drops it on blur.
 *
 * maxLength (optional) applies to the converted slug, not the native
 * attribute, which would cut the raw text short before it was converted.
 */
export function SlugInput({ name, id = name, value, onChange, onBlur, maxLength, invalid, className, ...props }) {
    const [draft, setDraft] = useState(value ?? "");
    const [previousValue, setPreviousValue] = useState(value);

    // Follow a value changed from outside, such as a form reset.
    if (value !== previousValue) {
        setPreviousValue(value);

        if (value !== slugify(draft, { trim: true })) {
            setDraft(value ?? "");
        }
    }

    function handleChange(e) {
        const input = e.target;
        const caret = input.selectionStart ?? input.value.length;
        const slug = slugify(input.value).slice(0, maxLength);
        const slugCaret = Math.min(slugify(input.value.slice(0, caret)).length, slug.length);

        input.value = slug;
        input.setSelectionRange(slugCaret, slugCaret);
        setDraft(slug);
        onChange(slugify(slug, { trim: true }));
    }

    function handleBlur(e) {
        setDraft(slugify(draft, { trim: true }));
        onBlur?.(e);
    }

    return (
        <Input
            id={id}
            name={name}
            value={draft}
            onChange={handleChange}
            onBlur={handleBlur}
            invalid={invalid}
            autoComplete="off"
            autoCapitalize="none"
            spellCheck={false}
            {...props}
            className={cn(controlClassName, className)}
        />
    );
}

/**
 * A titled group of form rows. Give it an `id` to link to it from an
 * "On this page" sidebar.
 */
export function FormSection({ id, legend, children }) {
    return (
        <Fieldset
            id={id}
            className="flex scroll-mt-4 flex-col gap-6 border-t border-amber-600/40 pt-6 first:border-t-0 first:pt-0"
        >
            <Legend className="font-serif text-xl text-white">{legend}</Legend>
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">{children}</div>
        </Fieldset>
    );
}

/**
 * Lists every validation error as a link to its field. It takes focus
 * after a failed submit so keyboard and screen reader users land on it.
 */
export function ErrorSummary({ errors, summaryRef, id = "form-error-summary" }) {
    const fields = Object.keys(errors);

    if (fields.length === 0) {
        return null;
    }

    return (
        <div
            ref={summaryRef}
            tabIndex={-1}
            aria-labelledby={`${id}-title`}
            className="rounded border-l-4 border-red-500 bg-red-100 p-4 text-red-800 focus:outline-2 focus:outline-offset-2 focus:outline-red-400"
        >
            <h2 id={`${id}-title`} className="font-semibold">
                {fields.length === 1 ? "Fix 1 problem to continue" : `Fix ${fields.length} problems to continue`}
            </h2>
            <ul className="mt-2 list-disc pl-5 text-sm">
                {fields.map((field) => (
                    <li key={field}>
                        <a href={`#${field}`} className="underline hover:no-underline">
                            {errors[field]}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * The first error for an id-list field, whether Laravel keyed it on the list
 * ("phase_ids") or on one of its items ("phase_ids.0").
 */
export function firstError(errors, field) {
    if (errors[field]) {
        return errors[field];
    }

    const itemKey = Object.keys(errors).find((key) => key.startsWith(`${field}.`));

    return itemKey ? errors[itemKey] : undefined;
}

export function SaveButton({ processing, label, processingLabel = "Saving…", disabled = false }) {
    return (
        <div>
            <PrimaryButton type="submit" processing={processing} disabled={disabled}>
                {processing ? processingLabel : label}
            </PrimaryButton>
        </div>
    );
}
