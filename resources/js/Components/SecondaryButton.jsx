export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-md border border-theme-border bg-theme-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-theme-ink shadow-sm transition duration-150 ease-in-out hover:bg-theme-bg focus:outline-none focus:ring-2 focus:ring-theme-primary/40 focus:ring-offset-2 focus:ring-offset-theme-surface disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
