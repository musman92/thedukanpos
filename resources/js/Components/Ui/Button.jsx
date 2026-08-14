const variants = {
    primary:
        'bg-[var(--color-primary)] text-[var(--color-on-primary)] hover:bg-[var(--color-primary-hover)] border-transparent',
    secondary:
        'bg-theme-surface text-theme-ink border-theme-border hover:bg-theme-bg',
    danger:
        'bg-[var(--color-danger)] text-white border-transparent hover:brightness-110',
    ghost:
        'bg-transparent text-theme-ink-soft border-transparent hover:bg-theme-bg hover:text-theme-ink',
};

const sizes = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-5 py-2.5 text-sm',
};

export default function Button({
    type = 'button',
    variant = 'primary',
    size = 'md',
    className = '',
    disabled = false,
    children,
    ...props
}) {
    return (
        <button
            type={type}
            disabled={disabled}
            className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-0 ${variants[variant] || variants.primary} ${sizes[size] || sizes.md} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
