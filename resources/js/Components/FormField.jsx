import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

export default function FormField({ label, htmlFor, error, required = false, className = '', children }) {
    return (
        <div className={className}>
            {label && (
                <InputLabel htmlFor={htmlFor} className="mb-1.5" required={required}>
                    {label}
                </InputLabel>
            )}
            {children}
            {error && <InputError message={error} className="mt-2" />}
        </div>
    );
}
