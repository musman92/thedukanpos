export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-transparent bg-[var(--color-danger)] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-theme-danger/40 focus:ring-offset-2 focus:ring-offset-theme-surface ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
