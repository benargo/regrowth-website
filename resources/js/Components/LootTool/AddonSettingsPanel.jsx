import { Field, Fieldset, Label, Legend } from "@headlessui/react";
import { CheckboxInput } from "@/Components/FormControls";
import cn from "@/Helpers/cn";

/**
 * A framed group of addon settings with a `legend`. When `disabled`, every control inside is
 * disabled and the frame shows `disabledReason` as its title and beneath the legend. `error`
 * shows beneath the controls.
 */
export default function AddonSettingsPanel({ legend, disabled = false, disabledReason, error, children }) {
    return (
        <Fieldset
            disabled={disabled}
            title={disabled ? disabledReason : undefined}
            className="bg-brown-800/40 overflow-hidden rounded-md border border-amber-700/70"
        >
            <div className="flex flex-col gap-1 border-b border-amber-700/50 px-4 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:px-5">
                <Legend className="font-semibold text-white">{legend}</Legend>
                <p className={cn("text-sm text-gray-400", !disabled && "hidden")}>{disabledReason}</p>
            </div>
            {children}
            <p className={cn("border-t border-red-400/40 px-4 py-3 text-sm text-red-300 sm:px-5", !error && "hidden")}>
                {error}
            </p>
        </Fieldset>
    );
}

/**
 * A list of `options`, each a labelled checkbox ticked when its id is in `selectedIds`.
 * onChange receives the new list of ids. `renderLabel` draws an option's label (its name by
 * default). With no options, `emptyMessage` shows instead.
 */
export function AddonSettingsChecklist({
    name,
    options,
    selectedIds,
    onChange,
    emptyMessage,
    renderLabel = (option) => option.name,
}) {
    if (options.length === 0) {
        return <p className="px-4 py-8 text-center text-sm text-gray-400 sm:px-5">{emptyMessage}</p>;
    }

    const toggle = (id, isChecked) =>
        onChange(isChecked ? [...selectedIds, id] : selectedIds.filter((selectedId) => selectedId !== id));

    return (
        <ul className="divide-y divide-amber-700/30">
            {options.map((option) => (
                <li key={option.id}>
                    <Field className="flex items-center gap-4 px-4 py-2 data-disabled:opacity-60 sm:px-5">
                        <CheckboxInput
                            id={`${name}-${option.id}`}
                            name={`${name}[]`}
                            checked={selectedIds.includes(option.id)}
                            onChange={(isChecked) => toggle(option.id, isChecked)}
                        />
                        <Label
                            htmlFor={`${name}-${option.id}`}
                            className="min-w-0 flex-1 cursor-pointer text-gray-100 data-disabled:cursor-not-allowed"
                        >
                            {renderLabel(option)}
                        </Label>
                    </Field>
                </li>
            ))}
        </ul>
    );
}
