import { forwardRef } from 'react';

const base =
    'w-full rounded-lg border border-theme-border bg-theme-surface px-3.5 py-2.5 text-sm text-theme-ink placeholder:text-theme-ink-muted shadow-none outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 disabled:cursor-not-allowed disabled:opacity-60';

const Input = forwardRef(function Input(
    { className = '', type = 'text', error = false, ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            type={type}
            className={`${base} ${error ? 'border-theme-danger focus:border-theme-danger focus:ring-theme-danger/20' : ''} ${className}`}
            {...props}
        />
    );
});

export function TextArea({ className = '', error = false, rows = 4, ...props }) {
    return (
        <textarea
            rows={rows}
            className={`${base} resize-y ${error ? 'border-theme-danger focus:border-theme-danger focus:ring-theme-danger/20' : ''} ${className}`}
            {...props}
        />
    );
}

export function Field({ label, required = false, error, hint, className = '', children }) {
    return (
        <div className={className}>
            {label && (
                <label className="mb-1.5 block text-sm font-medium text-theme-ink">
                    {label}
                    {required && <span className="ml-0.5 text-theme-danger">*</span>}
                </label>
            )}
            {children}
            {hint && !error && <p className="mt-1 text-xs text-theme-ink-muted">{hint}</p>}
            {error && <p className="mt-1 text-xs text-theme-danger">{error}</p>}
        </div>
    );
}

export default Input;
