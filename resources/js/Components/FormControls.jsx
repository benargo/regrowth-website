import { Button, Description, Field, Fieldset, Label, Legend, Select } from "@headlessui/react";

export const controlClassName =
    "block w-full rounded border border-ink-600 bg-ground-800 px-4 py-2 text-white placeholder-secondary-400 " +
    "data-focus:border-ink-400 data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-ink-400 " +
    "data-invalid:border-red-400";

export const buttonClassName =
    "bg-ink-800 data-focus:outline-ink-400 data-hover:bg-ink-900 inline-flex items-center gap-2 rounded-md px-4 py-2 " +
    "text-sm font-semibold text-white data-disabled:opacity-50 data-focus:outline-2 data-focus:outline-offset-2";

/** A quiet text link beside a form's buttons, such as Cancel, Back or Skip. */
export const linkClassName =
    "text-secondary-300 focus-visible:outline-ink-400 rounded px-2 py-2 text-sm underline hover:text-white " +
    "focus-visible:outline-2 focus-visible:outline-offset-2";

/**
 * A labelled HeadlessUI field. The hint and the error are both Descriptions,
 * so the control's aria-describedby announces them.
 */
export function FormRow({ htmlFor, label, required = false, hint, error, className = "", children }) {
    return (
        <Field className={`flex flex-col gap-1.5 ${className}`}>
            <Label htmlFor={htmlFor} className="text-secondary-300 text-sm font-medium">
                {label}
                {required && (
                    <span aria-hidden="true" className="ml-1 text-red-300">
                        *
                    </span>
                )}
            </Label>
            {children}
            {hint && <Description className="text-secondary-300 text-sm">{hint}</Description>}
            {error && <Description className="text-sm text-red-300">{error}</Description>}
        </Field>
    );
}

export function OptionSelect({ name, id = name, value, onChange, options, placeholder, invalid, required = false }) {
    return (
        <Select
            id={id}
            name={name}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            invalid={invalid}
            required={required}
            className={controlClassName}
        >
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </Select>
    );
}

export function FormSection({ legend, children }) {
    return (
        <Fieldset className="border-ink-600/40 flex flex-col gap-6 border-t pt-6 first:border-t-0 first:pt-0">
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

export function SaveButton({ processing, label, processingLabel = "Saving…" }) {
    return (
        <div>
            <Button type="submit" disabled={processing} className={buttonClassName}>
                {processing ? processingLabel : label}
            </Button>
        </div>
    );
}
