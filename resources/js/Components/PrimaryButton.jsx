export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-transparent bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[var(--color-on-primary)] transition duration-150 ease-in-out hover:bg-[var(--color-primary-hover)] focus:bg-[var(--color-primary-hover)] focus:outline-none focus:ring-2 focus:ring-theme-primary/40 focus:ring-offset-2 focus:ring-offset-theme-surface ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
