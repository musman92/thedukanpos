export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-theme-border bg-theme-surface text-theme-primary shadow-sm focus:ring-theme-primary/20 ' +
                className
            }
        />
    );
}
